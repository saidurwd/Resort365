<?php

namespace Modules\Payroll\Http\Controllers;

use App\Support\Reports\CsvExport;
use App\Support\Tenancy\PropertyContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\AuditTrail;
use Modules\Payroll\Actions\CalculatePayroll;
use Modules\Payroll\Actions\CreatePayrollRun;
use Modules\Payroll\Actions\DeletePayrollRun;
use Modules\Payroll\Actions\PayPayrollRun;
use Modules\Payroll\Actions\SendPayslips;
use Modules\Payroll\Actions\SubmitPayrollRun;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\PayMethod;
use Modules\Payroll\Enums\RunStatus;
use Modules\Payroll\Exceptions\PayrollRuleViolated;
use Modules\Payroll\Http\Requests\PayrollRunRequest;
use Modules\Payroll\Http\Requests\PayRunRequest;
use Modules\Payroll\Models\PayrollRun;
use Modules\Payroll\Models\SalaryComponent;
use Modules\Payroll\Services\PayrollReports;
use Modules\Property\Contracts\PropertyDirectory;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Payroll → Payroll runs: the runs of the current property, a run's page (payslips, totals, warnings) and its steps:
 * calculate, send for approval, pay, email the payslips, and the register and bank file downloads.
 */
class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PropertyContext $property,
        private readonly PropertyDirectory $properties,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', PayrollRun::class);
        $propertyId = $this->propertyId();

        return view('payroll::runs.index', [
            'runs' => PayrollRun::query()->where('property_id', $propertyId)->orderByDesc('period')->get(), 'canCreate' => $request->user()?->can('payroll.run.create') ?? false, 'subtitle' => $this->properties->find($propertyId)->name ?? null,
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', PayrollRun::class);
        $today = CarbonImmutable::parse($this->properties->find($this->propertyId())->businessDate ?? now()->toDateString());

        return view('payroll::runs.create', [
            'months' => collect(range(0, 5))->mapWithKeys(fn (int $back): array => [$today->startOfMonth()->subMonths($back)->format('Y-m') => $today->startOfMonth()->subMonths($back)->format('F Y')])->all(),
            'annual' => SalaryComponent::query()->where('frequency', ComponentFrequency::Annual->value)->where('is_active', true)->orderBy('sort_order')->pluck('name', 'code')->all(), 'subtitle' => $this->properties->find($this->propertyId())->name ?? null,
        ]);
    }

    public function store(PayrollRunRequest $request, CreatePayrollRun $create): RedirectResponse
    {
        Gate::authorize('create', PayrollRun::class);

        try {
            $run = $create->handle($this->propertyId(), (string) $request->validated('month'), (array) $request->validated('include_annual', []), $request->validated('notes'), $request->user()?->id);
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.runs.show', $run)->with('success', __('Payroll run :no opened. Calculate it next.', ['no' => $run->run_no]));
    }

    public function show(Request $request, PayrollRun $run, ApprovalEngine $approvals, AuditTrail $audit): View
    {
        Gate::authorize('view', $run);

        return view('payroll::runs.show', [
            'run' => $run->load('payslips'), 'approval' => $run->approval_request_id !== null ? $approvals->find($run->approval_request_id) : null, 'methods' => PayMethod::options(), 'today' => now()->toDateString(),
            'can' => ['create' => $request->user()?->can('create', PayrollRun::class) ?? false, 'pay' => $request->user()?->can('pay', $run) ?? false, 'export' => $request->user()?->can('export', $run) ?? false,
                'email' => $request->user()?->can('email', $run) ?? false, 'payslips' => $request->user()?->can('payroll.payslip.view') ?? false],
            'trail' => $audit->for($run), 'subtitle' => $this->properties->find($run->property_id)->name ?? null,
        ]);
    }

    public function calculate(PayrollRun $run, CalculatePayroll $calculate): RedirectResponse
    {
        Gate::authorize('update', $run);

        try {
            $calculate->handle($run);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.runs.show', $run)->with('success', __('Payroll calculated for :count employees.', ['count' => $run->refresh()->employees_count]));
    }

    public function submit(Request $request, PayrollRun $run, SubmitPayrollRun $submit): RedirectResponse
    {
        Gate::authorize('update', $run);

        try {
            $result = $submit->handle($run, (int) $request->user()?->id);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.runs.show', $run)->with('success', $result->status === RunStatus::Approved ? __('Payroll run :no is approved.', ['no' => $run->run_no]) : __('Payroll run :no is waiting for approval.', ['no' => $run->run_no]));
    }

    public function pay(PayRunRequest $request, PayrollRun $run, PayPayrollRun $pay): RedirectResponse
    {
        Gate::authorize('pay', $run);

        try {
            $pay->handle($run, PayMethod::from((string) $request->validated('payment_method')), (string) $request->validated('paid_on'), $request->validated('payment_reference'), (int) $request->user()?->id);
        } catch (PayrollRuleViolated $exception) {
            return back()->withInput()->with('error', $exception->getMessage());
        }

        return to_route('payroll.runs.show', $run)->with('success', __('Payroll run :no is paid.', ['no' => $run->run_no]));
    }

    public function email(PayrollRun $run, SendPayslips $send, Request $request): RedirectResponse
    {
        Gate::authorize('email', $run);

        try {
            $result = $send->handle($run, $request->boolean('again'));
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        $message = trans_choice(':count payslip is on its way.|:count payslips are on their way.', $result['queued']);

        if ($result['no_email'] !== []) {
            $message .= ' '.__('No email address for: :names.', ['names' => implode(', ', $result['no_email'])]);
        }

        if ($result['already'] > 0) {
            $message .= ' '.trans_choice(':count payslip was sent before and skipped.|:count payslips were sent before and skipped.', $result['already']);
        }

        return to_route('payroll.runs.show', $run)->with($result['queued'] > 0 ? 'success' : 'error', $message);
    }

    public function register(PayrollRun $run, PayrollReports $reports): StreamedResponse
    {
        Gate::authorize('export', $run);

        return CsvExport::response($reports->register($run), 'payroll-register-'.$run->period->format('Y-m').'.csv');
    }

    public function bankFile(PayrollRun $run, PayrollReports $reports): StreamedResponse|RedirectResponse
    {
        Gate::authorize('export', $run);

        if (! in_array($run->status, [RunStatus::Approved, RunStatus::Paid], true)) {
            return back()->with('error', __('The bank file is made once the run is approved.'));
        }

        return CsvExport::response($reports->bankFile($run)['data'], 'salary-transfer-'.$run->period->format('Y-m').'.csv');
    }

    public function destroy(PayrollRun $run, DeletePayrollRun $delete): RedirectResponse
    {
        Gate::authorize('delete', $run);

        try {
            $delete->handle($run);
        } catch (PayrollRuleViolated $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('payroll.runs.index')->with('success', __('Payroll run :no deleted.', ['no' => $run->run_no]));
    }

    private function propertyId(): int
    {
        return $this->property->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
