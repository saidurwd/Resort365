<?php

namespace Modules\Procurement\Services\Reports;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\Procurement\Enums\PurchaseOrderStatus;
use Modules\Procurement\Enums\VendorBillKind;
use Modules\Procurement\Enums\VendorBillStatus;
use Modules\Procurement\Models\GoodsReceiptLine;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseOrderLine;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorBillLine;
use Modules\Procurement\Models\VendorPayment;
use Modules\Procurement\Services\ItemLabels;

/**
 * Procurement → Reports (Step 5.8): open purchase orders, goods still to arrive, a vendor's ledger, payables aging per
 * vendor, purchase history by item and vendor, and purchase price variance. Each answers `title`, `columns`, `rows`,
 * `totals`, `summary` and `note` for the report table and the CSV download.
 */
class ProcurementReports
{
    /** Order statuses still waiting for goods. */
    private const array OPEN = [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent, PurchaseOrderStatus::Partial];

    /** Bill statuses that are owed: approved, partly paid or paid. */
    private const array OWED = [VendorBillStatus::Approved, VendorBillStatus::Partial, VendorBillStatus::Paid];

    public function __construct(private readonly ItemLabels $labels) {}

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function openOrders(int $propertyId, string $today): array
    {
        $orders = PurchaseOrder::query()->with(['vendor', 'lines'])->where('property_id', $propertyId)->whereIn('status', $this->values(self::OPEN))->orderBy('expected_on')->orderBy('id')->get();
        $rows = [];
        $open = BigDecimal::zero();
        $ordered = BigDecimal::zero();

        foreach ($orders as $order) {
            $outstanding = BigDecimal::zero();

            foreach ($order->lines as $line) {
                $left = BigDecimal::of($line->quantity)->minus($line->received_quantity);
                $outstanding = $outstanding->plus($left->isPositive() ? $left->multipliedBy($line->unit_price)->toScale(2, RoundingMode::HalfUp) : '0');
            }

            $ordered = $ordered->plus($order->subtotal);
            $open = $open->plus($outstanding);
            $rows[] = [$order->order_no, $order->vendor->name, $order->ordered_on->format('d M Y'), $order->expected_on?->format('d M Y') ?? '—', $order->status->label(), $this->money((string) $order->subtotal), $this->money((string) $outstanding), $this->overdue($order->expected_on?->toDateString(), $today)];
        }

        return [
            'title' => __('Open purchase orders'), 'columns' => [__('Order'), __('Vendor'), __('Ordered'), __('Expected'), __('Status'), __('Value'), __('Still to receive'), __('Days overdue')], 'rows' => $rows,
            'totals' => [__('Total'), '', '', '', '', $this->money((string) $ordered), $this->money((string) $open), ''], 'summary' => [['label' => __('Open orders'), 'value' => (string) count($rows)], ['label' => __('Still to receive'), 'value' => $this->money((string) $open)]],
            'note' => __('Approved, sent and partly received orders, before tax, at the order prices.'),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function pendingReceipts(int $propertyId, string $today): array
    {
        $orders = PurchaseOrder::query()->with(['vendor', 'lines'])->where('property_id', $propertyId)->whereIn('status', $this->values(self::OPEN))->orderBy('expected_on')->orderBy('id')->get();
        $items = $this->labels->for($orders->flatMap(fn (PurchaseOrder $order) => $order->lines->pluck('item_id')));
        $rows = [];
        $late = 0;

        foreach ($orders as $order) {
            foreach ($order->lines as $line) {
                $left = BigDecimal::of($line->quantity)->minus($line->received_quantity);

                if (! $left->isPositive()) {
                    continue;
                }

                $days = $this->overdue($order->expected_on?->toDateString(), $today);
                $late += $days !== '—' && (int) $days > 0 ? 1 : 0;
                $rows[] = [$order->order_no, $order->vendor->name, ($items[$line->item_id]->name ?? '#'.$line->item_id), $this->quantity((string) $line->quantity), $this->quantity((string) $line->received_quantity), $this->quantity((string) $left).' '.($items[$line->item_id]->baseUnit ?? ''), $order->expected_on?->format('d M Y') ?? '—', $days];
            }
        }

        return [
            'title' => __('Pending goods receipts'), 'columns' => [__('Order'), __('Vendor'), __('Item'), __('Ordered'), __('Received'), __('Still to arrive'), __('Expected'), __('Days overdue')], 'rows' => $rows, 'totals' => null,
            'summary' => [['label' => __('Lines to arrive'), 'value' => (string) count($rows)], ['label' => __('Overdue'), 'value' => (string) $late]], 'note' => __('Order lines with goods still to arrive, oldest expected date first.'),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function vendorLedger(int $vendorId, string $from, string $to): array
    {
        $vendor = Vendor::query()->find($vendorId);
        $entries = [];

        foreach (VendorBill::query()->where('vendor_id', $vendorId)->whereIn('status', $this->values(self::OWED))->where('bill_date', '<=', $to)->get() as $bill) {
            $entries[] = ['date' => $bill->bill_date->toDateString(), 'order' => 1, 'type' => __('Bill'), 'ref' => $bill->bill_no.($bill->vendor_reference ? ' · '.$bill->vendor_reference : ''), 'debit' => BigDecimal::zero(), 'credit' => BigDecimal::of($bill->total)];
        }

        foreach (PurchaseReturn::query()->with('lines')->where('vendor_id', $vendorId)->where('returned_on', '<=', $to)->get() as $return) {
            $owed = $return->lines->reduce(fn (BigDecimal $sum, $line): BigDecimal => $sum->plus($line->billed_value)->plus($line->tax_value), BigDecimal::zero());

            if ($owed->isPositive()) {
                $entries[] = ['date' => $return->returned_on->toDateString(), 'order' => 2, 'type' => __('Debit note'), 'ref' => $return->debit_note_no, 'debit' => $owed, 'credit' => BigDecimal::zero()];
            }
        }

        foreach (VendorPayment::query()->where('vendor_id', $vendorId)->where('paid_on', '<=', $to)->get() as $payment) {
            $entries[] = ['date' => $payment->paid_on->toDateString(), 'order' => 3, 'type' => BigDecimal::of($payment->allocated_amount)->isZero() ? __('Advance') : __('Payment'), 'ref' => $payment->payment_no.($payment->reference ? ' · '.$payment->reference : ''), 'debit' => BigDecimal::of($payment->amount), 'credit' => BigDecimal::zero()];
        }

        usort($entries, fn (array $a, array $b): int => [$a['date'], $a['order']] <=> [$b['date'], $b['order']]);
        $balance = BigDecimal::zero();
        $rows = [];

        foreach ($entries as $entry) {
            $balance = $balance->plus($entry['credit'])->minus($entry['debit']);

            if ($entry['date'] < $from) {
                continue;
            }

            $rows[] = [CarbonImmutable::parse($entry['date'])->format('d M Y'), $entry['type'], $entry['ref'], $entry['debit']->isZero() ? '' : $this->money((string) $entry['debit']), $entry['credit']->isZero() ? '' : $this->money((string) $entry['credit']), $this->money((string) $balance)];
        }

        $opening = BigDecimal::zero();

        foreach ($entries as $entry) {
            if ($entry['date'] < $from) {
                $opening = $opening->plus($entry['credit'])->minus($entry['debit']);
            }
        }

        array_unshift($rows, ['', __('Opening balance'), '', '', '', $this->money((string) $opening)]);

        return [
            'title' => __('Vendor ledger: :vendor', ['vendor' => $vendor->name ?? '—']), 'columns' => [__('Date'), __('Type'), __('Reference'), __('Paid or credited'), __('Billed'), __('Balance owed')], 'rows' => $rows, 'totals' => ['', '', __('Closing balance'), '', '', $this->money((string) $balance)],
            'summary' => [['label' => __('Owed'), 'value' => $this->money((string) $balance)]], 'note' => __('Approved bills add to what is owed; payments (advances too) and debit notes for billed goods take from it. A negative balance is an advance.'),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function vendorAging(string $asOf): array
    {
        $buckets = ['current' => BigDecimal::zero(), 'd30' => BigDecimal::zero(), 'd60' => BigDecimal::zero(), 'd90' => BigDecimal::zero(), 'over' => BigDecimal::zero()];
        $vendors = [];

        foreach (VendorBill::query()->with('vendor')->whereIn('status', [VendorBillStatus::Approved->value, VendorBillStatus::Partial->value])->where('bill_date', '<=', $asOf)->get() as $bill) {
            $owed = BigDecimal::of($bill->total)->minus($bill->paid_amount);
            $late = max(0, (int) CarbonImmutable::parse($bill->due_on->toDateString())->diffInDays(CarbonImmutable::parse($asOf), false));
            $bucket = match (true) {
                $late === 0 => 'current', $late <= 30 => 'd30', $late <= 60 => 'd60', $late <= 90 => 'd90', default => 'over',
            };
            $vendors[$bill->vendor_id] ??= ['name' => $bill->vendor->name, 'buckets' => $buckets, 'advance' => BigDecimal::zero(), 'credits' => BigDecimal::zero()];
            $vendors[$bill->vendor_id]['buckets'][$bucket] = $vendors[$bill->vendor_id]['buckets'][$bucket]->plus($owed);
        }

        foreach (VendorPayment::query()->with('vendor')->where('paid_on', '<=', $asOf)->get() as $payment) {
            $left = BigDecimal::of($payment->amount)->minus($payment->allocated_amount);

            if ($left->isPositive()) {
                $vendors[$payment->vendor_id] ??= ['name' => $payment->vendor->name, 'buckets' => $buckets, 'advance' => BigDecimal::zero(), 'credits' => BigDecimal::zero()];
                $vendors[$payment->vendor_id]['advance'] = $vendors[$payment->vendor_id]['advance']->plus($left);
            }
        }

        foreach (PurchaseReturn::query()->with(['lines', 'vendor'])->where('returned_on', '<=', $asOf)->get() as $return) {
            $owed = $return->lines->reduce(fn (BigDecimal $sum, $line): BigDecimal => $sum->plus($line->billed_value)->plus($line->tax_value), BigDecimal::zero());

            if ($owed->isPositive()) {
                $vendors[$return->vendor_id] ??= ['name' => $return->vendor->name, 'buckets' => $buckets, 'advance' => BigDecimal::zero(), 'credits' => BigDecimal::zero()];
                $vendors[$return->vendor_id]['credits'] = $vendors[$return->vendor_id]['credits']->plus($owed);
            }
        }

        uasort($vendors, fn (array $a, array $b): int => $a['name'] <=> $b['name']);
        $rows = [];
        $sums = array_merge($buckets, ['open' => BigDecimal::zero(), 'advance' => BigDecimal::zero(), 'credits' => BigDecimal::zero(), 'net' => BigDecimal::zero()]);

        foreach ($vendors as $vendor) {
            $open = array_reduce($vendor['buckets'], fn (BigDecimal $sum, BigDecimal $value): BigDecimal => $sum->plus($value), BigDecimal::zero());
            $net = $open->minus($vendor['advance'])->minus($vendor['credits']);
            $rows[] = [$vendor['name'], ...array_map(fn (BigDecimal $value): string => $this->money((string) $value), array_values($vendor['buckets'])), $this->money((string) $open), $this->money((string) $vendor['advance']), $this->money((string) $vendor['credits']), $this->money((string) $net)];

            foreach ($vendor['buckets'] as $key => $value) {
                $sums[$key] = $sums[$key]->plus($value);
            }

            $sums['open'] = $sums['open']->plus($open);
            $sums['advance'] = $sums['advance']->plus($vendor['advance']);
            $sums['credits'] = $sums['credits']->plus($vendor['credits']);
            $sums['net'] = $sums['net']->plus($net);
        }

        return [
            'title' => __('Payables aging by vendor'), 'columns' => [__('Vendor'), __('Not due'), __('1–30 days'), __('31–60 days'), __('61–90 days'), __('Over 90 days'), __('Open bills'), __('Advances'), __('Debit notes'), __('Net payable')], 'rows' => $rows,
            'totals' => [__('Total'), ...array_map(fn (BigDecimal $value): string => $this->money((string) $value), [$sums['current'], $sums['d30'], $sums['d60'], $sums['d90'], $sums['over'], $sums['open'], $sums['advance'], $sums['credits'], $sums['net']])],
            'summary' => [['label' => __('Net payable'), 'value' => $this->money((string) $sums['net'])]], 'note' => __('Open bills aged by days past their due date. Net payable = open bills − advances − debit notes for billed goods, which is what the ledger\'s accounts payable holds.'),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function purchaseHistory(int $propertyId, string $from, string $to, ?int $vendorId): array
    {
        $lines = GoodsReceiptLine::query()->with('receipt')->whereHas('receipt', fn ($query) => $query->where('property_id', $propertyId)->whereBetween('received_on', [$from, $to])->when($vendorId !== null, fn ($inner) => $inner->where('vendor_id', $vendorId)))->get();
        $vendors = Vendor::query()->whereIn('id', $lines->map(fn (GoodsReceiptLine $line): int => $line->receipt->vendor_id)->unique())->pluck('name', 'id');
        $items = $this->labels->for($lines->pluck('item_id'));
        $groups = [];

        foreach ($lines->sortBy(fn (GoodsReceiptLine $line): string => $line->receipt->received_on->toDateString().str_pad((string) $line->id, 9, '0', STR_PAD_LEFT)) as $line) {
            $key = $line->item_id.'|'.$line->receipt->vendor_id;
            $groups[$key] ??= ['item' => $line->item_id, 'vendor' => $line->receipt->vendor_id, 'quantity' => BigDecimal::zero(), 'value' => BigDecimal::zero(), 'last_price' => '0', 'last' => ''];
            $net = BigDecimal::of($line->accepted_quantity)->minus($line->returned_quantity);
            $groups[$key]['quantity'] = $groups[$key]['quantity']->plus($net);
            $groups[$key]['value'] = $groups[$key]['value']->plus($net->multipliedBy($line->unit_cost));
            $groups[$key]['last_price'] = (string) $line->unit_cost;
            $groups[$key]['last'] = $line->receipt->received_on->format('d M Y');
        }

        uasort($groups, fn (array $a, array $b): int => [$items[$a['item']]->name ?? '', $vendors[$a['vendor']] ?? ''] <=> [$items[$b['item']]->name ?? '', $vendors[$b['vendor']] ?? '']);
        $rows = [];
        $total = BigDecimal::zero();

        foreach ($groups as $group) {
            $average = $group['quantity']->isPositive() ? $group['value']->dividedBy($group['quantity'], 4, RoundingMode::HalfUp) : BigDecimal::zero();
            $total = $total->plus($group['value']);
            $rows[] = [($items[$group['item']]->name ?? '#'.$group['item']), $vendors[$group['vendor']] ?? '—', $this->quantity((string) $group['quantity']).' '.($items[$group['item']]->baseUnit ?? ''), number_format((float) (string) $average, 4), number_format((float) $group['last_price'], 4), $group['last'], $this->money((string) $group['value'])];
        }

        return [
            'title' => __('Purchase history'), 'columns' => [__('Item'), __('Vendor'), __('Quantity received'), __('Average price'), __('Last price'), __('Last delivery'), __('Value')], 'rows' => $rows, 'totals' => [__('Total'), '', '', '', '', '', $this->money((string) $total)],
            'summary' => [['label' => __('Bought'), 'value' => $this->money((string) $total)]], 'note' => __('Goods received between :from and :to, less returns, at the order prices, before tax.', ['from' => CarbonImmutable::parse($from)->format('d M Y'), 'to' => CarbonImmutable::parse($to)->format('d M Y')]),
        ];
    }

    /**
     * @return array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
     */
    public function priceVariance(int $propertyId, string $from, string $to, ?int $vendorId): array
    {
        $lines = VendorBillLine::query()->with(['bill.vendor'])->whereNotNull('purchase_order_line_id')->whereHas('bill', fn ($query) => $query->where('property_id', $propertyId)->where('kind', VendorBillKind::Purchase->value)->whereIn('status', $this->values(self::OWED))
            ->whereBetween('bill_date', [$from, $to])->when($vendorId !== null, fn ($inner) => $inner->where('vendor_id', $vendorId)))->get();
        $orderLines = PurchaseOrderLine::query()->whereIn('id', $lines->pluck('purchase_order_line_id'))->get()->keyBy('id');
        $items = $this->labels->for($lines->pluck('item_id')->filter());
        $rows = [];
        $total = BigDecimal::zero();

        foreach ($lines as $line) {
            $ordered = $orderLines->get($line->purchase_order_line_id);
            $difference = BigDecimal::of($line->unit_price)->minus($ordered->unit_price ?? $line->unit_price);

            if ($difference->isZero()) {
                continue;
            }

            $value = $difference->multipliedBy($line->quantity)->toScale(2, RoundingMode::HalfUp);
            $total = $total->plus($value);
            $rows[] = [$line->bill->bill_no, $line->bill->vendor->name, ($items[$line->item_id]->name ?? '#'.$line->item_id), $this->quantity((string) $line->quantity), number_format((float) ($ordered->unit_price ?? 0), 4), number_format((float) $line->unit_price, 4), number_format((float) (string) $difference, 4), $this->money((string) $value)];
        }

        return [
            'title' => __('Purchase price variance'), 'columns' => [__('Bill'), __('Vendor'), __('Item'), __('Quantity'), __('Order price'), __('Billed price'), __('Difference per unit'), __('Variance')], 'rows' => $rows, 'totals' => [__('Total'), '', '', '', '', '', '', $this->money((string) $total)],
            'summary' => [['label' => __('Variance'), 'value' => $this->money((string) $total)], ['label' => __('Lines'), 'value' => (string) count($rows)]], 'note' => __('Approved goods bills whose price differs from the order. A positive variance is paid above the order price; the ledger books it to purchase price variance.'),
        ];
    }

    /**
     * @param  list<PurchaseOrderStatus|VendorBillStatus>  $statuses
     * @return list<string>
     */
    private function values(array $statuses): array
    {
        return array_map(fn (PurchaseOrderStatus|VendorBillStatus $status): string => $status->value, $statuses);
    }

    private function overdue(?string $expected, string $today): string
    {
        if ($expected === null) {
            return '—';
        }

        return (string) max(0, (int) CarbonImmutable::parse($expected)->diffInDays(CarbonImmutable::parse($today), false));
    }

    private function money(string $amount): string
    {
        return number_format((float) $amount, 2);
    }

    private function quantity(string $amount): string
    {
        return rtrim(rtrim(number_format((float) $amount, 4, '.', ''), '0'), '.') ?: '0';
    }
}
