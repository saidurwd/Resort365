<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use InvalidArgumentException;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\SplitMode;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\PosPayment;

/**
 * An order's billing as the POS bill screen shows it (JSON): the lines with their discounts, the bill
 * discount, a preview of one bill while the order is open, and the printed bills with what is due and
 * the payments taken.
 */
class BillPresenter
{
    public function __construct(
        private readonly OrderBilling $billing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(PosOrder $order): array
    {
        $order->refresh()->load('table');
        $lines = PosOrderLine::query()->where('pos_order_id', $order->id)->where('status', '!=', OrderLineStatus::Voided->value)->orderBy('id')->get();
        $bills = PosBill::query()->where('pos_order_id', $order->id)->where(fn ($query) => $query->where('status', '!=', BillStatus::Voided->value)->orWhereNotNull('settled_at'))
            ->with('payments')->orderBy('sequence')->get();

        try {
            $preview = $order->isOpen() && $lines->isNotEmpty() ? $this->billing->bills($order, SplitMode::None)[0] : null;
        } catch (InvalidArgumentException) {
            $preview = null;
        }

        return [
            'id' => $order->id, 'order_no' => $order->order_no, 'type' => $order->order_type->value, 'status' => $order->status->value, 'status_label' => $order->status->label(),
            'where' => $order->table ? __('Table :number', ['number' => $order->table->number]) : $order->order_type->label(), 'covers' => $order->covers,
            'discount' => $this->discount($order), 'pending' => $lines->where('status', OrderLineStatus::Pending)->count(),
            'seats' => $lines->pluck('seat_no')->filter()->unique()->sort()->values()->all(),
            'lines' => $lines->map(fn (PosOrderLine $line): array => [
                'id' => $line->id, 'name' => $line->name_snapshot.($line->variant_snapshot ? ' ('.$line->variant_snapshot.')' : ''), 'quantity' => $line->quantity,
                'seat' => $line->seat_no, 'line_total' => $line->line_total, 'discount' => $this->discount($line), 'meal_plan' => $line->package_redemption_id !== null,
            ])->values()->all(),
            'preview' => $preview,
            'redemption' => ($redemption = PackageRedemption::query()->where('pos_order_id', $order->id)->first()) instanceof PackageRedemption ? [
                'guest' => $redemption->guest_name, 'code' => $redemption->reservation_code, 'period' => $redemption->meal_period->label(), 'covers' => $redemption->covers(),
                'items' => $lines->whereNotNull('package_redemption_id')->count(),
            ] : null,
            'bills' => $bills->map(fn (PosBill $bill): array => [
                'id' => $bill->id, 'bill_no' => $bill->bill_no, 'label' => $bill->split_label, 'status' => $bill->status->value, 'status_label' => $bill->status->label(),
                'subtotal' => $bill->subtotal, 'discount_total' => $bill->discount_total, 'service_charge' => $bill->service_charge, 'tax_total' => $bill->tax_total,
                'grand_total' => $bill->grand_total, 'tip_total' => $bill->tip_total, 'paid_total' => $bill->paid_total,
                'due' => (string) BigDecimal::of($bill->grand_total)->minus($bill->paid_total)->toScale(2), 'complimentary' => $bill->is_complimentary,
                'payments' => $bill->payments->map(fn (PosPayment $payment): array => [
                    'method' => $payment->method->label(), 'amount' => $payment->amount, 'tip' => $payment->tip, 'change' => $payment->change_given, 'reference' => $payment->reference,
                    'refund' => $payment->refund_of_id !== null, 'charged_to' => $payment->charged_to,
                ])->values()->all(),
                'print_url' => route('pos.bills.print', $bill), 'receipt_url' => route('pos.bills.receipt', $bill),
            ])->values()->all(),
        ];
    }

    /**
     * @return array{type: string, value: string, reason: string|null}|null
     */
    private function discount(PosOrder|PosOrderLine $model): ?array
    {
        return $model->discount_type === null ? null : ['type' => $model->discount_type->value, 'value' => (string) $model->discount_value, 'reason' => $model->discount_reason];
    }
}
