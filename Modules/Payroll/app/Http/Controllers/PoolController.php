<?php

namespace Modules\Payroll\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\HR\Contracts\EmployeeDirectory;
use Modules\Payroll\Actions\DeletePool;
use Modules\Payroll\Actions\FinalisePool;
use Modules\Payroll\Actions\PreparePool;
use Modules\Payroll\Actions\ReopenPool;
use Modules\Payroll\Actions\SavePoolParticipants;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;
use Modules\Payroll\Enums\PoolStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\PoolParticipantsRequest;
use Modules\Payroll\Http\Requests\PoolRequest;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\PoolParticipant;
use Modules\Payroll\Models\ServiceChargePool;
use Modules\Payroll\Services\PoolSettings;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Payroll → Service charge and tips: the pools of the current property, working one out for a month, sending it to payroll,
 * and who takes part with how many points.
 */
class PoolController extends Controller
{
    public function __construct(
        private readonly PropertyContext $property,
        private readonly PropertyDirectory $properties,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', ServiceChargePool::class);
        $propertyId = $this->propertyId();

        return view('payroll::pools.index', [
            'pools' => ServiceChargePool::query()->where('property_id', $propertyId)->orderByDesc('period')->orderBy('kind')->get(), 'canManage' => $request->user()?->can('payroll.pool.manage') ?? false, 'subtitle' => $this->properties->find($propertyId)->name ?? null,
        ]);
    }

    public function create(PoolSettings $settings): View
    {
        Gate::authorize('create', ServiceChargePool::class);
        $propertyId = $this->propertyId();
        $today = CarbonImmutable::parse($this->properties->find($propertyId)->businessDate ?? now()->toDateString());

        return view('payroll::pools.create', [
            'months' => collect(range(0, 5))->mapWithKeys(fn (int $back): array => [$today->startOfMonth()->subMonths($back)->format('Y-m') => $today->startOfMonth()->subMonths($back)->format('F Y')])->all(),
            'kinds' => PoolKind::options(), 'rules' => PoolRule::options(), 'rule' => $settings->rule($propertyId)->value,
            'percents' => [PoolKind::ServiceCharge->value => $settings->staffPercent(PoolKind::ServiceCharge, $propertyId), PoolKind::Tips->value => $settings->staffPercent(PoolKind::Tips, $propertyId)],
            'subtitle' => $this->properties->find($propertyId)->name ?? null,
        ]);
    }

    public function store(PoolRequest $request, PreparePool $prepare): RedirectResponse
    {
        Gate::authorize('create', ServiceChargePool::class);

        try {
            $pool = $prepare->handle(
                $this->propertyId(), (string) $request->validated('month'), PoolKind::from((string) $request->validated('kind')), $request->filled('rule') ? PoolRule::from((string) $request->validated('rule')) : null,
                $request->filled('staff_percent') ? (string) $request->validated('staff_percent') : null, $request->user()?->id,
            );
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.pools.show', $pool)->with('success', __('The pool is worked out. Check the shares, then send it to payroll.'));
    }

    public function show(Request $request, ServiceChargePool $pool, AuditTrail $audit): View
    {
        Gate::authorize('view', $pool);
        $run = PayrollRun::query()->where('property_id', $pool->property_id)->whereDate('period', $pool->period->toDateString())->first();
        $pool->load('allocations');

        return view('payroll::pools.show', [
            'pool' => $pool, 'run' => $run, 'canManage' => $request->user()?->can('update', $pool) ?? false, 'trail' => $audit->for($pool), 'subtitle' => $this->properties->find($pool->property_id)->name ?? null,
            'waiting' => $pool->status === PoolStatus::Finalised && $pool->allocations->contains(fn ($allocation): bool => $allocation->payslip_id === null),
        ]);
    }

    public function recalculate(Request $request, ServiceChargePool $pool, PreparePool $prepare): RedirectResponse
    {
        Gate::authorize('update', $pool);

        try {
            $prepare->handle($pool->property_id, $pool->period->format('Y-m'), $pool->kind, $pool->rule, (string) $pool->staff_percent, $request->user()?->id);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.pools.show', $pool)->with('success', __('The pool is worked out again from the ledger.'));
    }

    public function finalise(Request $request, ServiceChargePool $pool, FinalisePool $finalise): RedirectResponse
    {
        Gate::authorize('update', $pool);

        try {
            $again = $finalise->handle($pool, $request->user()?->id);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = __('The pool is ready for payroll. The payroll run adds the shares when it is calculated.');

        if ($again) {
            $message .= ' '.__('The month\'s payroll run is already calculated: calculate it again to include them.');
        }

        return to_route('payroll.pools.show', $pool)->with('success', $message);
    }

    public function reopen(ServiceChargePool $pool, ReopenPool $reopen): RedirectResponse
    {
        Gate::authorize('update', $pool);

        try {
            $reopen->handle($pool);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.pools.show', $pool)->with('success', __('The pool is open again. Calculate the payroll run again once it is sent back.'));
    }

    public function destroy(ServiceChargePool $pool, DeletePool $delete): RedirectResponse
    {
        Gate::authorize('delete', $pool);

        try {
            $delete->handle($pool);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.pools.index')->with('success', __('The pool is deleted.'));
    }

    public function participants(Request $request, EmployeeDirectory $employees): View
    {
        Gate::authorize('viewAny', ServiceChargePool::class);
        $propertyId = $this->propertyId();
        $saved = PoolParticipant::query()->get()->keyBy('employee_id');

        return view('payroll::pools.participants', [
            'rows' => collect($employees->forProperty($propertyId))->map(function ($employee) use ($saved): array {
                $participant = $saved->get($employee->id);

                return ['employee' => $employee, 'eligible' => $participant instanceof PoolParticipant ? $participant->is_eligible : true, 'points' => $participant instanceof PoolParticipant ? (string) $participant->points : '1.00'];
            })->all(),
            'canManage' => $request->user()?->can('payroll.pool.manage') ?? false, 'subtitle' => $this->properties->find($propertyId)->name ?? null,
        ]);
    }

    public function saveParticipants(PoolParticipantsRequest $request, SavePoolParticipants $save): RedirectResponse
    {
        Gate::authorize('create', ServiceChargePool::class);

        try {
            $count = $save->handle(array_values(array_map(
                fn (array $row): array => ['employee_id' => (int) $row['employee_id'], 'eligible' => (bool) ($row['eligible'] ?? false), 'points' => (string) $row['points']],
                (array) $request->validated('participants'),
            )));
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.pools.participants')->with('success', trans_choice(':count person saved.|:count people saved.', $count).' '.__('Work a pool out again to use the new points.'));
    }

    private function propertyId(): int
    {
        return $this->property->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
