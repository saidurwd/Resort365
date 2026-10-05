<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use InvalidArgumentException;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\SplitMode;
use Modules\Restaurant\Enums\TableStatus;
use Modules\Restaurant\Events\TableStatusChanged;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosBillLine;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\OrderBilling;
use Modules\Restaurant\Services\PosNumbers;

/**
 * Prints an order's bill (the pre-check), whole or split (ARCHITECTURE §5.10.7): the bills are numbered
 * per outlet without gaps and their money frozen (rule 6); the order and its table show the bill as
 * printed, and nothing more can be added until the order is reopened. Items not yet sent must be sent
 * (or removed) first.
 */
class PrintBill extends Action
{
    public function __construct(
        private readonly OrderBilling $billing,
        private readonly PosNumbers $numbers,
    ) {}

    /**
     * @param  array{bills?: int, amounts?: list<string>, assignments?: array<int, array<int, int>>}  $options
     * @return list<PosBill>
     *
     * @throws PosNotAllowed
     */
    public function handle(PosOrder $order, SplitMode $mode, array $options, int $userId): array
    {
        return $this->transaction(function () use ($order, $mode, $options, $userId): array {
            $locked = PosOrder::query()->lockForUpdate()->with('outlet')->findOrFail($order->id);

            if (! $locked->isOpen()) {
                throw new PosNotAllowed(__('This order is not open.'));
            }

            if (PosOrderLine::query()->where('pos_order_id', $locked->id)->where('status', OrderLineStatus::Pending->value)->exists()) {
                throw new PosNotAllowed(__('Send or remove the items not yet sent to the kitchen first.'));
            }

            try {
                $drafts = $this->billing->bills($locked, $mode, $options);
            } catch (InvalidArgumentException $exception) {
                throw new PosNotAllowed($exception->getMessage());
            }

            $bills = [];

            foreach ($drafts as $draft) {
                [$sequence, $number] = $this->numbers->nextBill($locked->outlet);
                $bill = PosBill::query()->create([
                    'property_id' => $locked->property_id, 'outlet_id' => $locked->outlet_id, 'pos_order_id' => $locked->id, 'sequence' => $sequence, 'bill_no' => $number,
                    'business_date' => $locked->business_date, 'split_label' => $draft['label'], 'subtotal' => $draft['subtotal'], 'discount_total' => $draft['discount_total'],
                    'service_charge' => $draft['service_charge'], 'tax_total' => $draft['tax_total'], 'grand_total' => $draft['grand_total'], 'tax_breakdown' => $draft['tax_breakdown'],
                    'status' => BillStatus::Printed, 'print_count' => 1, 'printed_at' => now(), 'created_by' => $userId,
                ]);

                foreach ($draft['lines'] as $line) {
                    PosBillLine::query()->create([
                        'property_id' => $locked->property_id, 'pos_bill_id' => $bill->id, 'pos_order_line_id' => $line['id'], 'name_snapshot' => $line['name'],
                        'quantity' => $line['quantity'], 'amount' => $line['amount'], 'discount' => $line['discount'], 'tax' => $line['tax'], 'gross' => $line['gross'],
                    ]);
                }

                $bills[] = $bill;
            }

            $locked->forceFill(['status' => OrderStatus::BillPrinted])->save();

            if ($locked->dining_table_id !== null) {
                DiningTable::query()->whereKey($locked->dining_table_id)->update(['status' => TableStatus::BillPrinted->value]);
            }

            TableStatusChanged::dispatch($locked->tenant_id, $locked->outlet_id, $locked->dining_table_id === null ? [] : [$locked->dining_table_id]);

            return $bills;
        });
    }
}
