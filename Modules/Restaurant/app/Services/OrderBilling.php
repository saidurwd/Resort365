<?php

namespace Modules\Restaurant\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Modules\Core\Contracts\Settings;
use Modules\Core\Contracts\TaxEngine;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Enums\SplitMode;
use Modules\Restaurant\Models\MenuItem;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * An order's money as bills (ARCHITECTURE §5.10.7). Items covered by a meal plan cost nothing. Each line
 * not voided is discounted (its own discount, then its share of the bill discount: DiscountAllocator) and
 * taxed on what is left with the item's tax category, else the outlet's (Core's TaxEngine, inclusive or
 * exclusive as the outlet prices).
 * BillSplitter then makes one bill or a split, which always adds up to the order. The service charge is
 * the tax lines whose code is restaurant.service_charge_code; the rest is tax.
 */
class OrderBilling
{
    public function __construct(
        private readonly TaxEngine $taxes,
        private readonly Settings $settings,
        private readonly DiscountAllocator $discounts,
        private readonly BillSplitter $splitter,
    ) {}

    /**
     * The order's lines in cents, ready to split, and its totals.
     *
     * @return array{inclusive: bool, lines: list<array{id: int, quantity: int, seat: int|null, name: string, amount: int, discount: int, taxes: array<string, int>}>,
     *   taxNames: array<string, array{name: string, rate: string}>, base: int, itemDiscount: int, orderDiscount: int}
     */
    public function lines(PosOrder $order): array
    {
        $order->loadMissing('outlet');
        $lines = PosOrderLine::query()->where('pos_order_id', $order->id)->where('status', '!=', OrderLineStatus::Voided->value)->orderBy('id')->get();
        $categories = MenuItem::query()->whereIn('id', $lines->pluck('menu_item_id'))->pluck('tax_category_id', 'id');
        $inclusive = $order->outlet->prices_include_tax;

        // Items covered by a guest's meal plan go on the bill at nothing (§5.10.9).
        $amounts = $lines->map(fn (PosOrderLine $line): int => $line->package_redemption_id !== null ? 0 : $this->cents($line->line_total))->values()->all();
        $own = $lines->map(fn (PosOrderLine $line, int $index): int => $line->discount_type === null ? 0
            : $this->discounts->amount($amounts[$index], $line->discount_type, (string) $line->discount_value))->values()->all();
        $after = array_map(fn (int $amount, int $discount): int => $amount - $discount, $amounts, $own);
        $orderDiscount = $order->discount_type === null ? 0 : $this->discounts->amount(array_sum($after), $order->discount_type, (string) $order->discount_value);
        $shares = $this->discounts->share($orderDiscount, $after);
        $taxNames = [];
        $result = [];

        foreach ($lines->values() as $index => $line) {
            $discount = $own[$index] + $shares[$index];
            $breakdown = $this->taxes->calculate($this->money($amounts[$index] - $discount), $categories[$line->menu_item_id] ?? $order->outlet->default_tax_category_id, $inclusive);
            $taxes = [];

            foreach ($breakdown->taxes as $tax) {
                $taxes[$tax->code] = ($taxes[$tax->code] ?? 0) + $this->cents($tax->amount);
                $taxNames[$tax->code] = ['name' => $tax->name, 'rate' => $tax->rate];
            }

            $result[] = [
                'id' => $line->id, 'quantity' => $line->quantity, 'seat' => $line->seat_no,
                'name' => $line->name_snapshot.($line->variant_snapshot ? ' ('.$line->variant_snapshot.')' : '').($line->package_redemption_id !== null ? ' · '.__('meal plan') : ''),
                'amount' => $amounts[$index], 'discount' => $discount, 'taxes' => $taxes,
            ];
        }

        return ['inclusive' => $inclusive, 'lines' => $result, 'taxNames' => $taxNames, 'base' => array_sum($amounts), 'itemDiscount' => array_sum($own), 'orderDiscount' => $orderDiscount];
    }

    /**
     * The bills an order would print as: label, subtotal, discount, service charge, tax, grand total,
     * tax breakdown and lines (decimal strings).
     *
     * @param  array{bills?: int, amounts?: list<string>, assignments?: array<int, array<int, int>>}  $options
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException when the split does not fit the order
     */
    public function bills(PosOrder $order, SplitMode $mode, array $options = []): array
    {
        $data = $this->lines($order);
        $lines = array_map(fn (array $line): array => ['id' => $line['id'], 'quantity' => $line['quantity'], 'amount' => $line['amount'], 'discount' => $line['discount'], 'taxes' => $line['taxes']], $data['lines']);

        if ($lines === []) {
            throw new InvalidArgumentException(__('The order has nothing to bill.'));
        }

        [$bills, $labels] = match ($mode) {
            SplitMode::None => [$this->splitter->single($lines, $data['inclusive']), [null]],
            SplitMode::Equal => $this->equal($lines, $data['inclusive'], (int) ($options['bills'] ?? 2)),
            SplitMode::Amount => $this->byAmount($lines, $data['inclusive'], $options['amounts'] ?? []),
            SplitMode::Seat => $this->bySeat($data['lines'], $lines, $data['inclusive']),
            SplitMode::Item => $this->byItem($lines, $data['inclusive'], $options['assignments'] ?? [], (int) ($options['bills'] ?? 2)),
        };

        $names = array_column($data['lines'], 'name', 'id');
        $serviceCode = (string) $this->settings->get('restaurant.service_charge_code');

        return array_map(function (array $bill, ?string $label) use ($names, $serviceCode, $data): array {
            $service = 0;
            $tax = 0;
            $breakdown = [];

            foreach ($bill['taxes'] as $code => $amount) {
                $code = (string) $code;
                $code === $serviceCode ? $service += $amount : $tax += $amount;
                $breakdown[] = ['code' => $code, 'name' => $data['taxNames'][$code]['name'] ?? $code, 'rate' => $data['taxNames'][$code]['rate'] ?? '', 'amount' => $this->money($amount)];
            }

            return [
                'label' => $label, 'inclusive' => $data['inclusive'],
                'subtotal' => $this->money($bill['amount']), 'discount_total' => $this->money($bill['discount']), 'service_charge' => $this->money($service),
                'tax_total' => $this->money($tax), 'grand_total' => $this->money($bill['gross']), 'tax_breakdown' => $breakdown,
                'lines' => array_map(fn (array $line): array => [
                    'id' => $line['id'], 'name' => $names[$line['id']] ?? '', 'quantity' => $line['quantity'], 'amount' => $this->money($line['amount']),
                    'discount' => $this->money($line['discount']), 'tax' => $this->money($line['tax']), 'gross' => $this->money($line['gross']),
                ], $bill['lines']),
            ];
        }, $bills, $labels);
    }

    /**
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @return array{list<array<string, mixed>>, list<string|null>}
     */
    private function equal(array $lines, bool $inclusive, int $count): array
    {
        if ($count < 2 || $count > 20) {
            throw new InvalidArgumentException(__('Split between 2 and 20 people.'));
        }

        return [$this->splitter->byWeights($lines, $inclusive, array_fill(0, $count, 1)),
            array_map(fn (int $index): string => __(':n of :count', ['n' => $index + 1, 'count' => $count]), range(0, $count - 1))];
    }

    /**
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @param  list<string>  $amounts
     * @return array{list<array<string, mixed>>, list<string|null>}
     */
    private function byAmount(array $lines, bool $inclusive, array $amounts): array
    {
        $cents = array_map($this->cents(...), array_values(array_filter($amounts, is_numeric(...))));
        $total = array_sum(array_column($this->splitter->single($lines, $inclusive), 'gross'));

        if (count($cents) < 2 || min($cents) <= 0) {
            throw new InvalidArgumentException(__('Enter at least two amounts above zero.'));
        }

        if (array_sum($cents) !== $total) {
            throw new InvalidArgumentException(__('The amounts add up to :sum; the order comes to :total.', ['sum' => $this->money(array_sum($cents)), 'total' => $this->money($total)]));
        }

        return [$this->splitter->byWeights($lines, $inclusive, $cents),
            array_map(fn (int $index): string => __('Bill :n', ['n' => $index + 1]), array_keys($cents))];
    }

    /**
     * Each seat gets its lines; lines without a seat are shared equally between the seats.
     *
     * @param  list<array{id: int, quantity: int, seat: int|null, name: string, amount: int, discount: int, taxes: array<string, int>}>  $full
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @return array{list<array<string, mixed>>, list<string|null>}
     */
    private function bySeat(array $full, array $lines, bool $inclusive): array
    {
        $seats = array_values(array_unique(array_filter(array_column($full, 'seat'))));
        sort($seats);

        if (count($seats) < 2) {
            throw new InvalidArgumentException(__('Give the items seats first: a split by seat needs at least two seats.'));
        }

        $index = array_flip($seats);
        $assignments = [];

        foreach ($full as $line) {
            $assignments[$line['id']] = $line['seat'] !== null ? [$index[$line['seat']] => 1] : array_fill(0, count($seats), 1);
        }

        return [$this->splitter->byLines($lines, $inclusive, $assignments, count($seats)),
            array_map(fn (int $seat): string => __('Seat :seat', ['seat' => $seat]), $seats)];
    }

    /**
     * Lines (or some of their units) given to bills; every unit must be on a bill.
     *
     * @param  list<array{id: int, quantity: int, amount: int, discount: int, taxes: array<string, int>}>  $lines
     * @param  array<int, array<int, int>>  $assignments
     * @return array{list<array<string, mixed>>, list<string|null>}
     */
    private function byItem(array $lines, bool $inclusive, array $assignments, int $count): array
    {
        if ($count < 2 || $count > 20) {
            throw new InvalidArgumentException(__('Split into 2 to 20 bills.'));
        }

        foreach ($lines as $line) {
            $given = array_sum(array_map(intval(...), $assignments[$line['id']] ?? []));

            if ($given !== $line['quantity']) {
                throw new InvalidArgumentException(__('Put every item on a bill.'));
            }
        }

        $assignments = array_map(fn (array $bills): array => array_map(intval(...), $bills), $assignments);

        return [$this->splitter->byLines($lines, $inclusive, $assignments, $count),
            array_map(fn (int $index): string => __('Bill :n', ['n' => $index + 1]), range(0, $count - 1))];
    }

    private function cents(string $amount): int
    {
        return BigDecimal::of($amount)->multipliedBy(100)->toScale(0, RoundingMode::HalfUp)->toInt();
    }

    private function money(int $cents): string
    {
        return (string) BigDecimal::ofUnscaledValue($cents, 2);
    }
}
