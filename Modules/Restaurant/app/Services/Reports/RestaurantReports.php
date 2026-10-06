<?php

namespace Modules\Restaurant\Services\Reports;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Enums\MealPeriod;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\OrderType;
use Modules\Restaurant\Enums\PaymentMethod;
use Modules\Restaurant\Models\ManagerApproval;
use Modules\Restaurant\Models\MealEntitlementSnapshot;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\PackageRedemption;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Models\PosPayment;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\SessionCash;

/**
 * The restaurant reports (ARCHITECTURE §5.10.14) for a property, a range of business dates and optionally
 * one outlet. Each report is a title, columns, rows of text (money with two decimals), totals and summary
 * figures, so one screen shows them all and the same data exports as CSV. Settled bills count; bills voided
 * or reopened do not. Sums come from the pure SalesReport and MealPlanReport.
 *
 * @phpstan-type Report array{title: string, columns: list<string>, rows: list<list<string>>, totals: list<string>|null, summary: list<array{label: string, value: string}>, note: string|null}
 */
class RestaurantReports
{
    public const array SALES_BY = ['outlet', 'category', 'item', 'hour', 'waiter', 'method'];

    public function __construct(
        private readonly SalesReport $sales,
        private readonly MealPlanReport $meals,
        private readonly UserDirectory $users,
        private readonly PropertyDirectory $properties,
        private readonly ReservationLookup $reservations,
        private readonly SessionCash $cash,
    ) {}

    /**
     * Sales by outlet, category, item, hour, waiter or payment method, with covers and spend per cover.
     *
     * @return Report
     */
    public function sales(int $propertyId, string $from, string $to, ?int $outletId, string $by): array
    {
        $by = in_array($by, self::SALES_BY, true) ? $by : 'outlet';
        $bills = $this->bills($propertyId, $from, $to, $outletId, BillStatus::Settled, ['lines.line', 'order', 'outlet', 'payments']);
        $timezone = $this->properties->find($propertyId)->timezone ?? 'UTC';
        $names = $this->names();
        $items = MenuItem::query()->whereIn('id', $bills->flatMap(fn (PosBill $bill) => $bill->lines->pluck('line.menu_item_id'))->filter()->unique())->with('category')->get()->keyBy('id');
        $rows = [];

        foreach ($bills as $bill) {
            foreach ($bill->lines as $line) {
                $item = $items->get($line->line->menu_item_id);
                $rows[] = [
                    'label' => match ($by) {
                        'category' => $item?->category?->translated('name') ?? __('No category'),
                        'item' => $line->line->name_snapshot.($line->line->variant_snapshot ? ' ('.$line->line->variant_snapshot.')' : ''),
                        'hour' => CarbonImmutable::instance($bill->settled_at ?? $bill->printed_at)->setTimezone($timezone)->format('H').':00',
                        'waiter' => $names[$bill->order->waiter_id] ?? '—',
                        default => $bill->outlet->name,
                    },
                    'qty' => (float) $line->quantity, 'net' => $this->cents($line->amount) - $this->cents($line->discount), 'discount' => $this->cents($line->discount),
                    'tax' => $this->cents($line->tax), 'gross' => $this->cents($line->gross),
                ];
            }
        }

        $covers = (int) $bills->pluck('order')->unique('id')->sum('covers');
        $totalNet = array_sum(array_column($rows, 'net'));
        $tips = $bills->reduce(fn (BigDecimal $sum, PosBill $bill): BigDecimal => $sum->plus($bill->tip_total), BigDecimal::zero());
        $summary = [
            ['label' => __('Net sales'), 'value' => $this->money($totalNet)],
            ['label' => __('Bills'), 'value' => (string) $bills->count()],
            ['label' => __('Covers'), 'value' => (string) $covers],
            ['label' => __('Spend per cover'), 'value' => $this->money($this->sales->perCover($totalNet, $covers))],
            ['label' => __('Tips'), 'value' => (string) $tips->toScale(2)],
        ];

        if ($by === 'method') {
            $methods = $bills->flatMap(fn (PosBill $bill) => $bill->payments)->map(fn (PosPayment $payment): array => [
                'label' => $payment->method->label(), 'qty' => 1.0, 'net' => $this->cents($payment->amount), 'discount' => 0, 'tax' => 0, 'gross' => $this->cents($payment->amount) + $this->cents($payment->tip),
            ])->all();
            $grouped = $this->sales->group($methods);

            return $this->report(__('Sales by payment method'), [__('Method'), __('Payments'), __('Amount'), __('With tips'), __('Share %')],
                array_map(fn (array $group): array => [$group['label'], (string) (int) $group['qty'], $this->money($group['net']), $this->money($group['gross']), $group['share']], $grouped),
                [__('Total'), (string) (int) array_sum(array_column($grouped, 'qty')), $this->money(array_sum(array_column($grouped, 'net'))), $this->money(array_sum(array_column($grouped, 'gross'))), ''], $summary);
        }

        $grouped = $this->sales->group($rows, $by === 'hour');
        $labels = ['outlet' => __('Outlet'), 'category' => __('Category'), 'item' => __('Item'), 'hour' => __('Hour'), 'waiter' => __('Waiter')];

        return $this->report(__('Sales by :dimension', ['dimension' => mb_strtolower($labels[$by])]), [$labels[$by], __('Quantity'), __('Net sales'), __('Discounts'), __('Tax'), __('Gross'), __('Share %')],
            array_map(fn (array $group): array => [$group['label'], rtrim(rtrim(number_format($group['qty'], 3, '.', ''), '0'), '.'), $this->money($group['net']), $this->money($group['discount']),
                $this->money($group['tax']), $this->money($group['gross']), $group['share']], $grouped),
            [__('Total'), '', $this->money($totalNet), $this->money(array_sum(array_column($grouped, 'discount'))), $this->money(array_sum(array_column($grouped, 'tax'))),
                $this->money(array_sum(array_column($grouped, 'gross'))), ''], $summary, $by === 'item' || $by === 'category' ? __('Items on a guest\'s meal plan are listed at nothing.') : null);
    }

    /**
     * Who voided, discounted or comped what: voided items, discounts, complimentary bills, voided and
     * reopened bills, each with the person and the manager who approved it.
     *
     * @return Report
     */
    public function exceptions(int $propertyId, string $from, string $to, ?int $outletId): array
    {
        $names = $this->names();
        $approver = fn (?int $approvalId): string => $approvalId === null ? '' : ($names[ManagerApproval::query()->whereKey($approvalId)->value('approved_by')] ?? '');
        $orders = PosOrder::query()->where('property_id', $propertyId)->whereBetween('business_date', [$from, $to])->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))->with('outlet')->get()->keyBy('id');
        $lines = PosOrderLine::query()->whereIn('pos_order_id', $orders->keys())->where(fn ($query) => $query->where('status', OrderLineStatus::Voided->value)->orWhereNotNull('discount_type'))->get();
        $rows = [];

        foreach ($lines as $line) {
            $order = $orders->get($line->pos_order_id);

            if ($line->status === OrderLineStatus::Voided) {
                $rows[] = [$order->business_date->toDateString(), __('Voided item'), $order->outlet->name, $order->order_no.' · '.$line->quantity.' × '.$line->name_snapshot, $this->money($this->cents($line->line_total)),
                    $names[$line->voided_by] ?? '', trim(($line->void_reason?->label() ?? '').($line->void_note ? ': '.$line->void_note : '').($line->is_wastage ? ' ('.__('wastage').')' : '')), $approver($line->manager_approval_id)];
            }

            if ($line->discount_type !== null) {
                $rows[] = [$order->business_date->toDateString(), __('Item discount'), $order->outlet->name, $order->order_no.' · '.$line->name_snapshot, $this->discountText($line->discount_type->value, (string) $line->discount_value),
                    $names[$line->discount_by] ?? '', (string) $line->discount_reason, $approver($line->discount_approval_id)];
            }
        }

        foreach ($orders->filter(fn (PosOrder $order): bool => $order->discount_type !== null) as $order) {
            $rows[] = [$order->business_date->toDateString(), __('Bill discount'), $order->outlet->name, $order->order_no, $this->discountText($order->discount_type->value, (string) $order->discount_value),
                $names[$order->discount_by] ?? '', (string) $order->discount_reason, $approver($order->discount_approval_id)];
        }

        $bills = PosBill::query()->whereIn('pos_order_id', $orders->keys())->where(fn ($query) => $query->where('is_complimentary', true)->orWhere('status', BillStatus::Voided->value))->get();

        foreach ($bills as $bill) {
            $order = $orders->get($bill->pos_order_id);
            $where = [$bill->business_date->toDateString(), '', $order->outlet->name, $bill->bill_no.' · '.$order->order_no, $this->money($this->cents($bill->grand_total))];

            if ($bill->is_complimentary) {
                $rows[] = [...array_slice($where, 0, 1), $order->order_type === OrderType::StaffMeal ? __('Staff meal') : __('Complimentary bill'), ...array_slice($where, 2), $names[$bill->settled_by] ?? '',
                    trim(($bill->comp_reason?->label() ?? '').($bill->comp_note ? ': '.$bill->comp_note : '')), $approver($bill->manager_approval_id)];
            }

            if ($bill->status === BillStatus::Voided) {
                $rows[] = [...array_slice($where, 0, 1), $bill->settled_at !== null ? __('Voided bill') : __('Reopened bill'), ...array_slice($where, 2), $names[$bill->voided_by] ?? '', (string) $bill->void_reason,
                    $approver($bill->manager_approval_id)];
            }
        }

        usort($rows, fn (array $a, array $b): int => [$a[0], $a[1]] <=> [$b[0], $b[1]]);
        $counts = array_count_values(array_column($rows, 1));

        return $this->report(__('Exceptions: voids, discounts and complimentary bills'), [__('Date'), __('Type'), __('Outlet'), __('What'), __('Amount'), __('By'), __('Reason'), __('Approved by')], $rows, null,
            array_map(fn (string $type, int $count): array => ['label' => $type, 'value' => (string) $count], array_keys($counts), array_values($counts)));
    }

    /**
     * Bills charged to rooms and to company accounts.
     *
     * @return Report
     */
    public function roomCharges(int $propertyId, string $from, string $to, ?int $outletId): array
    {
        $payments = PosPayment::query()->where('property_id', $propertyId)->whereBetween('business_date', [$from, $to])->whereIn('method', [PaymentMethod::RoomCharge->value, PaymentMethod::CityLedger->value])
            ->whereNull('refund_of_id')->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))->with(['bill.outlet'])->orderBy('business_date')->orderBy('id')->get()
            ->filter(fn (PosPayment $payment): bool => $payment->bill->status !== BillStatus::Voided);
        $total = $payments->reduce(fn (BigDecimal $sum, PosPayment $payment): BigDecimal => $sum->plus($payment->amount), BigDecimal::zero());
        $byMethod = $payments->groupBy(fn (PosPayment $payment): string => $payment->method->label())->map(fn (Collection $group): string => (string) $group->reduce(fn (BigDecimal $sum, PosPayment $payment): BigDecimal => $sum->plus($payment->amount), BigDecimal::zero())->toScale(2));

        return $this->report(__('Room charges and account billing'), [__('Date'), __('Outlet'), __('Bill'), __('Charged to'), __('Method'), __('Amount')],
            $payments->map(fn (PosPayment $payment): array => [$payment->business_date->toDateString(), $payment->bill->outlet->name, $payment->bill->bill_no, (string) $payment->charged_to, $payment->method->label(), (string) $payment->amount])->values()->all(),
            [__('Total'), '', '', '', '', (string) $total->toScale(2)],
            $byMethod->map(fn (string $amount, string $method): array => ['label' => $method, 'value' => $amount])->values()->all());
    }

    /**
     * Meals included in meal plans against meals taken, per date and meal period (past days from the night
     * audit's snapshots, today from the stays in house now).
     *
     * @return Report
     */
    public function mealPlans(int $propertyId, string $from, string $to, ?int $outletId): array
    {
        $included = [];

        foreach (MealEntitlementSnapshot::query()->where('property_id', $propertyId)->whereBetween('business_date', [$from, $to])->get() as $snapshot) {
            $date = $snapshot->business_date->toDateString();
            $included[$date][$snapshot->meal_period->value] = ($included[$date][$snapshot->meal_period->value] ?? 0) + $snapshot->covers;
        }

        $today = $this->properties->find($propertyId)->businessDate ?? null;

        if ($today !== null && $today >= $from && $today <= $to && ! isset($included[$today])) {
            foreach ($this->reservations->mealEntitlements($propertyId, $today) as $entitlement) {
                foreach (MealPeriod::cases() as $period) {
                    if ($entitlement->covers($period->value) > 0) {
                        $included[$today][$period->value] = ($included[$today][$period->value] ?? 0) + $entitlement->covers($period->value);
                    }
                }
            }
        }

        $taken = [];

        foreach (PackageRedemption::query()->where('property_id', $propertyId)->whereBetween('business_date', [$from, $to])->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))->get() as $redemption) {
            $date = $redemption->business_date->toDateString();
            $taken[$date][$redemption->meal_period->value] = ($taken[$date][$redemption->meal_period->value] ?? 0) + $redemption->covers();
        }

        $rows = $this->meals->compare($included, $taken);

        return $this->report(__('Meal plans: included against taken'), [__('Date'), __('Meal'), __('Included'), __('Taken'), __('Not taken'), __('Over the plan')],
            array_map(fn (array $row): array => [$row['date'], MealPeriod::from($row['period'])->label(), (string) $row['included'], (string) $row['taken'], (string) $row['not_taken'], (string) $row['over']], $rows),
            [__('Total'), '', (string) array_sum(array_column($rows, 'included')), (string) array_sum(array_column($rows, 'taken')), (string) array_sum(array_column($rows, 'not_taken')), (string) array_sum(array_column($rows, 'over'))],
            [], $outletId !== null ? __('Included meals are not split by outlet; taken meals are for the outlet chosen.') : null);
    }

    /**
     * Every POS session with its takings and cash difference (X/Z history).
     *
     * @return Report
     */
    public function sessions(int $propertyId, string $from, string $to, ?int $outletId): array
    {
        $names = $this->names();
        $sessions = PosSession::query()->where('property_id', $propertyId)->whereBetween('business_date', [$from, $to])->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))
            ->with(['outlet', 'terminal'])->orderBy('business_date')->orderBy('id')->get();
        $variance = BigDecimal::zero();
        $rows = [];

        foreach ($sessions as $session) {
            $takings = $this->cash->byMethod($session);
            $variance = $variance->plus($session->cash_variance ?? '0');
            $rows[] = [$session->business_date->toDateString(), $session->outlet->name, $session->terminal->name, $names[$session->opened_by] ?? '', $names[$session->closed_by] ?? '',
                $this->money($this->cents($session->opening_float)), number_format((float) array_sum($takings), 2, '.', ''),
                $session->expected_cash !== null ? (string) $session->expected_cash : '', $session->counted_cash !== null ? (string) $session->counted_cash : '',
                $session->cash_variance !== null ? (string) $session->cash_variance : '', $session->status->label()];
        }

        return $this->report(__('POS sessions: X/Z history'), [__('Date'), __('Outlet'), __('Terminal'), __('Opened by'), __('Closed by'), __('Float'), __('Takings'), __('Expected cash'), __('Counted'), __('Over / short'), __('Status')],
            $rows, null, [['label' => __('Sessions'), 'value' => (string) count($rows)], ['label' => __('Cash over / short'), 'value' => (string) $variance->toScale(2)]]);
    }

    /**
     * Covers, orders and minutes seated per table.
     *
     * @return Report
     */
    public function turnover(int $propertyId, string $from, string $to, ?int $outletId): array
    {
        $orders = PosOrder::query()->where('property_id', $propertyId)->whereBetween('business_date', [$from, $to])->where('status', OrderStatus::Settled->value)->whereNotNull('dining_table_id')
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))->with(['table', 'outlet'])->get();
        $rows = $orders->groupBy('dining_table_id')->map(function (Collection $group): array {
            $minutes = $group->map(fn (PosOrder $order): int => (int) $order->opened_at->diffInMinutes($order->closed_at ?? $order->opened_at))->avg();

            return [$group->first()->outlet->name, (string) $group->first()->table?->number, (string) $group->count(), (string) $group->sum('covers'),
                (string) BigDecimal::of((string) $minutes)->toScale(0, RoundingMode::HalfUp)];
        })->sortBy([[0, 'asc'], [1, 'asc']])->values()->all();

        return $this->report(__('Table turnover'), [__('Outlet'), __('Table'), __('Orders'), __('Covers'), __('Average minutes seated')], $rows, null,
            [['label' => __('Orders'), 'value' => (string) $orders->count()], ['label' => __('Covers'), 'value' => (string) $orders->sum('covers')]]);
    }

    /**
     * @param  list<string>  $with  relations to load
     * @return Collection<int, PosBill>
     */
    private function bills(int $propertyId, string $from, string $to, ?int $outletId, BillStatus $status, array $with = []): Collection
    {
        return PosBill::query()->where('property_id', $propertyId)->whereBetween('business_date', [$from, $to])->where('status', $status->value)
            ->when($outletId, fn ($query) => $query->where('outlet_id', $outletId))->with($with)->orderBy('id')->get();
    }

    /**
     * @return array<int, string>
     */
    private function names(): array
    {
        return collect($this->users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name])->all();
    }

    private function discountText(string $type, string $value): string
    {
        return $type === 'percent' ? rtrim(rtrim($value, '0'), '.').'%' : $value;
    }

    private function cents(string|float|null $amount): int
    {
        return BigDecimal::of((string) ($amount ?? '0'))->multipliedBy(100)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    private function money(int $cents): string
    {
        return (string) BigDecimal::ofUnscaledValue($cents, 2);
    }

    /**
     * @param  list<string>  $columns
     * @param  list<list<string>>  $rows
     * @param  list<string>|null  $totals
     * @param  list<array{label: string, value: string}>  $summary
     * @return Report
     */
    private function report(string $title, array $columns, array $rows, ?array $totals, array $summary, ?string $note = null): array
    {
        return ['title' => $title, 'columns' => $columns, 'rows' => $rows, 'totals' => $totals, 'summary' => $summary, 'note' => $note];
    }
}
