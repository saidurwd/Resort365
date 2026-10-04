<?php

namespace Modules\Billing\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Billing\Actions\CloseShift;
use Modules\Billing\Actions\OpenShift;
use Modules\Billing\Enums\CashierShiftStatus;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Http\Requests\CloseShiftRequest;
use Modules\Billing\Http\Requests\OpenShiftRequest;
use Modules\Billing\Models\CashierShift;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\CashCount;
use Modules\Billing\Services\ShiftRegister;
use Modules\Billing\Services\ShiftsTable;
use Modules\Core\Contracts\Settings;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * Billing → My cashier shift (open with a float, close with a cash count), Cashier shifts (every
 * shift of the current property with its variance) and the printable shift report.
 */
class ShiftController extends Controller
{
    public function mine(Request $request, ShiftRegister $register, CloseShift $close, CashCount $count, Settings $settings, PropertyDirectory $properties): View
    {
        Gate::authorize('open', CashierShift::class);
        $propertyId = $this->propertyId();
        $userId = (int) $request->user()?->getAuthIdentifier();
        $shift = $register->openShift($userId, $propertyId);
        [$received, $refunded] = $shift instanceof CashierShift ? $close->cash($shift) : ['0.00', '0.00'];

        return view('billing::shifts.mine', [
            'shift' => $shift,
            'received' => $received,
            'refunded' => $refunded,
            'expected' => $shift instanceof CashierShift ? $count->expected($shift->opening_float, $received, $refunded) : null,
            'payments' => $shift instanceof CashierShift ? Payment::query()->where('cashier_shift_id', $shift->id)->latest('received_at')->get() : collect(),
            'denominations' => $count->denominations((string) $settings->get('billing.cash_denominations')),
            'recent' => CashierShift::query()->where('property_id', $propertyId)->where('user_id', $userId)->where('status', CashierShiftStatus::Closed->value)
                ->latest('closed_at')->limit(5)->get(),
            'property' => $properties->find($propertyId),
        ]);
    }

    public function open(OpenShiftRequest $request, OpenShift $open): RedirectResponse
    {
        try {
            $open->handle($this->propertyId(), (int) $request->user()?->getAuthIdentifier(), (string) $request->validated('opening_float'));
        } catch (PaymentNotAllowed $exception) {
            return to_route('billing.shifts.mine')->with('error', $exception->getMessage());
        }

        return to_route('billing.shifts.mine')->with('success', __('Shift opened. Payments you take now belong to it.'));
    }

    public function close(CloseShiftRequest $request, CashierShift $shift, CloseShift $close): RedirectResponse
    {
        try {
            $closed = $close->handle($shift, (array) $request->validated('count'), $request->validated('variance_reason'), (int) $request->user()?->getAuthIdentifier());
        } catch (PaymentNotAllowed $exception) {
            return to_route('billing.shifts.mine')->withInput()->with('error', $exception->getMessage());
        }

        return to_route('billing.shifts.show', $closed)->with('success', __('Shift closed.'));
    }

    public function index(PropertyDirectory $properties): View
    {
        Gate::authorize('viewAny', CashierShift::class);

        return view('billing::shifts.index', ['columns' => ShiftsTable::columns(), 'propertyName' => $properties->find($this->propertyId())->name ?? '']);
    }

    public function data(ShiftsTable $table): JsonResponse
    {
        Gate::authorize('viewAny', CashierShift::class);

        return $table->toJson($this->propertyId());
    }

    public function show(CashierShift $shift, CloseShift $close, CashCount $count, PropertyDirectory $properties, UserDirectory $users): View
    {
        Gate::authorize('view', $shift);
        [$received, $refunded] = $shift->isOpen() ? $close->cash($shift) : [(string) $shift->cash_received, (string) $shift->cash_refunded];
        $names = collect($users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]);

        return view('billing::shifts.show', [
            'shift' => $shift,
            'received' => $received,
            'refunded' => $refunded,
            'expected' => $shift->expected_cash ?? $count->expected($shift->opening_float, $received, $refunded),
            'payments' => Payment::query()->where('cashier_shift_id', $shift->id)->orderBy('received_at')->get(),
            'property' => $properties->find($shift->property_id),
            'cashier' => $names[$shift->user_id] ?? '#'.$shift->user_id,
            'closedBy' => $shift->closed_by !== null ? ($names[$shift->closed_by] ?? '#'.$shift->closed_by) : null,
        ]);
    }

    private function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
