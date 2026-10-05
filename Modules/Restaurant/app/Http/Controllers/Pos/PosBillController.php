<?php

namespace Modules\Restaurant\Http\Controllers\Pos;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use InvalidArgumentException;
use Modules\Core\Contracts\Settings;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Actions\CompBill;
use Modules\Restaurant\Actions\DiscountOrder;
use Modules\Restaurant\Actions\PrintBill;
use Modules\Restaurant\Actions\ReopenOrder;
use Modules\Restaurant\Actions\TakePayment;
use Modules\Restaurant\Actions\VoidBill;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\CompReason;
use Modules\Restaurant\Enums\DiscountType;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Enums\SplitMode;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Http\Requests\BillDiscountRequest;
use Modules\Restaurant\Http\Requests\BillExceptionRequest;
use Modules\Restaurant\Http\Requests\BillPaymentRequest;
use Modules\Restaurant\Http\Requests\BillSplitRequest;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\BillPresenter;
use Modules\Restaurant\Services\DiscountLimits;
use Modules\Restaurant\Services\ManagerApprovals;
use Modules\Restaurant\Services\OrderBilling;
use Modules\Restaurant\Services\PosContext;

/**
 * The POS bill screen (ARCHITECTURE §5.10.7, §10.3 "settle screen", §12 rule 15): discounts, the split
 * and its preview, printing the bills, payments, complimentary bills, reopening and voids. Every JSON
 * endpoint runs its Action and answers with the order's whole billing (BillPresenter).
 */
class PosBillController extends Controller
{
    public function show(PosOrder $order, PosContext $context, BillPresenter $presenter, ManagerApprovals $approvals, DiscountLimits $limits, Settings $settings, PropertyDirectory $properties): View|RedirectResponse
    {
        $this->authorizeOrder($order, $context);

        if (! in_array($order->status, [OrderStatus::Open, OrderStatus::BillPrinted, OrderStatus::Settled], true)) {
            return to_route('pos.floor')->with('error', __('Order :number is closed.', ['number' => $order->order_no]));
        }

        $terminal = $context->terminal();
        $user = auth()->user();
        $userId = (int) $user?->getAuthIdentifier();
        $approvers = fn (string $action): array => $approvals->approvers($terminal, $action, $userId);

        return view('restaurant::pos.bill', [
            'terminal' => $terminal,
            'order' => $order,
            'session' => $this->session($context),
            'currency' => $properties->find($terminal->property_id)->currencyCode ?? '',
            'autoLockMinutes' => (int) $settings->get('restaurant.pos_auto_lock_minutes', $terminal->property_id),
            'state' => [
                'billing' => $presenter->present($order),
                'modes' => array_map(fn (SplitMode $mode): array => ['value' => $mode->value, 'label' => $mode->label()], SplitMode::cases()),
                'methods' => array_map(fn (PaymentMethod $method): array => ['value' => $method->value, 'label' => $method->label()], PaymentMethod::tenders()),
                'compReasons' => array_map(fn (CompReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()], CompReason::cases()),
                'approvers' => [
                    'bill.discount' => $approvers(DiscountOrder::APPROVAL), 'bill.reopen' => $approvers(ReopenOrder::APPROVAL),
                    'bill.comp' => $approvers(CompBill::APPROVAL), 'bill.void' => $approvers(VoidBill::APPROVAL),
                ],
                'can' => [
                    'settle' => $user?->can('restaurant.bill.settle') ?? false, 'reopen' => $user?->can('restaurant.bill.reopen') ?? false,
                    'comp' => $user?->can('restaurant.bill.comp') ?? false, 'void' => $user?->can('restaurant.bill.void') ?? false,
                ],
                'discountLimit' => $limits->maxPercent($userId),
                'urls' => [
                    'discount' => route('pos.orders.discount', $order), 'preview' => route('pos.orders.bill.preview', $order), 'print' => route('pos.orders.bill.print', $order),
                    'reopen' => route('pos.orders.reopen', $order), 'pay' => route('pos.bills.pay', ['bill' => '__BILL__']), 'comp' => route('pos.bills.comp', ['bill' => '__BILL__']),
                    'void' => route('pos.bills.void', ['bill' => '__BILL__']), 'approve' => route('pos.approvals.store'), 'order' => route('pos.orders.show', $order),
                    'floor' => route('pos.floor'),
                ],
                'messages' => ['offline' => __('No connection. Try again.')],
            ],
        ]);
    }

    public function discount(BillDiscountRequest $request, PosOrder $order, PosContext $context, DiscountOrder $discount, BillPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);
        $line = $request->filled('line_id') ? PosOrderLine::query()->where('pos_order_id', $order->id)->find((int) $request->validated('line_id')) : null;

        if ($request->filled('line_id') && ! $line instanceof PosOrderLine) {
            return response()->json(['ok' => false, 'message' => __('This item is not on the order.')], 422);
        }

        return $this->respond($presenter, $order, fn () => $discount->handle($order, $line, $request->filled('type') ? DiscountType::from((string) $request->validated('type')) : null,
            $request->filled('value') ? (string) $request->validated('value') : null, $request->validated('reason'), (int) $request->user()?->getAuthIdentifier(),
            $request->filled('approval_id') ? (int) $request->validated('approval_id') : null));
    }

    public function preview(BillSplitRequest $request, PosOrder $order, PosContext $context, OrderBilling $billing): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        try {
            $bills = $billing->bills($order, SplitMode::from((string) $request->validated('mode')), $request->options());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'bills' => $bills]);
    }

    public function print(BillSplitRequest $request, PosOrder $order, PosContext $context, PrintBill $print, BillPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);
        $bills = [];

        return $this->respond($presenter, $order, function () use ($request, $order, $print, &$bills): void {
            $bills = $print->handle($order, SplitMode::from((string) $request->validated('mode')), $request->options(), (int) $request->user()?->getAuthIdentifier());
        }, function () use (&$bills): array {
            return ['print' => array_map(fn (PosBill $bill): string => route('pos.bills.print', ['bill' => $bill, 'first' => 1]), $bills)];
        });
    }

    public function reopen(BillExceptionRequest $request, PosOrder $order, PosContext $context, ReopenOrder $reopen, BillPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        return $this->respond($presenter, $order, fn (): PosOrder => $reopen->handle($order, (int) $request->user()?->getAuthIdentifier(), $request->user()?->can('restaurant.bill.reopen') ?? false,
            $request->filled('approval_id') ? (int) $request->validated('approval_id') : null));
    }

    public function pay(BillPaymentRequest $request, PosBill $bill, PosContext $context, TakePayment $take, BillPresenter $presenter): JsonResponse
    {
        $order = $this->authorizeBill($bill, $context);
        $session = $this->session($context);

        if (! $session instanceof PosSession) {
            return response()->json(['ok' => false, 'message' => __('Open a cash session on this terminal to take payments.')], 422);
        }

        return $this->respond($presenter, $order, fn (): PosPayment => $take->handle($bill, PaymentMethod::from((string) $request->validated('method')), (string) $request->validated('amount'),
            (string) ($request->validated('tip') ?? '0'), $request->filled('tendered') ? (string) $request->validated('tendered') : null, $request->validated('reference'),
            $session, (int) $request->user()?->getAuthIdentifier()));
    }

    public function comp(BillExceptionRequest $request, PosBill $bill, PosContext $context, CompBill $comp, BillPresenter $presenter): JsonResponse
    {
        $order = $this->authorizeBill($bill, $context);
        $session = $this->session($context);

        if (! $session instanceof PosSession) {
            return response()->json(['ok' => false, 'message' => __('Open a cash session on this terminal to take payments.')], 422);
        }

        return $this->respond($presenter, $order, fn (): PosBill => $comp->handle($bill, CompReason::from((string) $request->validated('comp_reason')), $request->validated('note'), $session,
            (int) $request->user()?->getAuthIdentifier(), $request->user()?->can('restaurant.bill.comp') ?? false, $request->filled('approval_id') ? (int) $request->validated('approval_id') : null));
    }

    public function void(BillExceptionRequest $request, PosBill $bill, PosContext $context, VoidBill $void, BillPresenter $presenter): JsonResponse
    {
        $order = $this->authorizeBill($bill, $context);
        $session = $this->session($context);

        if (! $session instanceof PosSession) {
            return response()->json(['ok' => false, 'message' => __('Open a cash session on this terminal to refund the payments.')], 422);
        }

        return $this->respond($presenter, $order, fn (): PosBill => $void->handle($bill, (string) $request->validated('reason'), $request->boolean('food_prepared', true), $session,
            (int) $request->user()?->getAuthIdentifier(), $request->user()?->can('restaurant.bill.void') ?? false, $request->filled('approval_id') ? (int) $request->validated('approval_id') : null));
    }

    /**
     * The bill (pre-check) on 80 mm paper; reprints are counted.
     */
    public function printView(Request $request, PosBill $bill, PosContext $context, PropertyDirectory $properties): View
    {
        $this->authorizeBill($bill, $context, 'view');

        if (! $request->boolean('first')) {
            $bill->forceFill(['print_count' => $bill->print_count + 1])->save();
        }

        return $this->document($bill, $properties, false);
    }

    /**
     * The receipt of a settled bill; every print after the first is marked COPY.
     */
    public function receipt(PosBill $bill, PosContext $context, PropertyDirectory $properties): View
    {
        $this->authorizeBill($bill, $context, 'view');
        abort_unless($bill->status !== BillStatus::Printed, 404);
        $bill->forceFill(['receipt_count' => $bill->receipt_count + 1])->save();

        return $this->document($bill, $properties, true);
    }

    private function document(PosBill $bill, PropertyDirectory $properties, bool $receipt): View
    {
        $bill->load(['lines', 'payments', 'outlet', 'order.table']);

        return view('restaurant::pos.bill-print', [
            'bill' => $bill, 'receipt' => $receipt, 'copy' => $receipt ? $bill->receipt_count > 1 : $bill->print_count > 1,
            'property' => $properties->find($bill->property_id), 'inclusive' => $bill->outlet->prices_include_tax,
        ]);
    }

    /**
     * Runs a billing Action and answers with the order's billing, or why it was refused.
     *
     * @param  (callable(): array<string, mixed>)|null  $extra
     */
    private function respond(BillPresenter $presenter, PosOrder $order, callable $action, ?callable $extra = null): JsonResponse
    {
        try {
            $action();
        } catch (PosNotAllowed $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage(), 'approval' => $exception->approval, 'billing' => $presenter->present($order)], 422);
        }

        return response()->json(['ok' => true, 'billing' => $presenter->present($order), ...($extra !== null ? $extra() : [])]);
    }

    private function session(PosContext $context): ?PosSession
    {
        return PosSession::query()->where('pos_terminal_id', $context->terminal()->id)->where('status', PosSessionStatus::Open->value)->first();
    }

    private function authorizeOrder(PosOrder $order, PosContext $context): void
    {
        abort_unless($order->outlet_id === $context->terminal()->outlet_id, 404);
        Gate::authorize('update', $order);
    }

    private function authorizeBill(PosBill $bill, PosContext $context, string $ability = 'settle'): PosOrder
    {
        abort_unless($bill->outlet_id === $context->terminal()->outlet_id, 404);
        Gate::authorize($ability, $bill);

        return $bill->order;
    }
}
