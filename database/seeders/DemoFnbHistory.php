<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Property\Models\Property;
use Modules\Restaurant\Services\FoodCosting;

/**
 * F&B history demo (Step 7.2): twelve weeks of settled restaurant bills at the outlets that have a price list, for the menu
 * engineering and trends reports. Dishes with a recipe sell in a pattern that puts some in each class (every second dish by
 * margin is a popular one, in both halves of the margin ranking), and a few dishes without a recipe sell too, so they show as
 * "cost unknown". Direct inserts: orders, lines, bills and bill lines only, with no payments, kitchen tickets or stock movements,
 * so it is history for the reports and not for the ledger.
 */
final class DemoFnbHistory
{
    private const int DAYS = 84;

    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function (Tenant $tenant) use ($propertyId): void {
            if (DB::table('pos_orders')->where('tenant_id', $tenant->id)->where('order_no', 'like', 'H%')->exists()) {
                return;
            }

            $rows = array_values(array_filter(app(FoodCosting::class)->rows($propertyId), fn (array $row): bool => $row['variant'] === null && (float) $row['price'] > 0));
            $waiter = (int) DB::table('users')->where('tenant_id', $tenant->id)->orderBy('id')->value('id');

            if ($rows === [] || $waiter === 0) {
                return;
            }

            mt_srand($propertyId * 4241);
            $property = Property::query()->findOrFail($propertyId);
            $today = CarbonImmutable::parse($property->business_date->toDateString());
            $byOutlet = [];

            foreach (self::weighted($rows) as $row) {
                $byOutlet[$row['outlet']->id][] = $row;
            }

            foreach ($byOutlet as $outletId => $menu) {
                if (count($menu) < 3) {
                    continue;
                }

                self::outlet($tenant->id, $propertyId, $outletId, $menu[0]['outlet']->bill_prefix, $waiter, $menu, $today);
            }
        });
    }

    /**
     * Each row with a weight: of the dishes with a recipe, ranked by margin, every second one is popular, so each half of the
     * ranking has popular and unpopular dishes; dishes without a recipe sell a little.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function weighted(array $rows): array
    {
        $costed = array_values(array_filter($rows, fn (array $row): bool => $row['has_recipe'] && $row['cost'] !== null));
        usort($costed, fn (array $a, array $b): int => ((float) $b['price'] - (float) $b['cost']) <=> ((float) $a['price'] - (float) $a['cost']));
        $out = [];

        foreach ($costed as $rank => $row) {
            $out[] = [...$row, 'weight' => $rank % 2 === 0 ? 12 : 3];
        }

        foreach (array_filter($rows, fn (array $row): bool => ! $row['has_recipe']) as $row) {
            $out[] = [...$row, 'weight' => 4];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $menu
     */
    private static function outlet(int $tenantId, int $propertyId, int $outletId, string $prefix, int $waiter, array $menu, CarbonImmutable $today): void
    {
        $sequence = (int) DB::table('pos_bills')->where('outlet_id', $outletId)->max('sequence');
        $busy = DB::table('outlets')->where('id', $outletId)->value('name') === 'Main Restaurant';
        $total = array_sum(array_column($menu, 'weight'));

        for ($day = $today->subDays(self::DAYS); $day->lt($today); $day = $day->addDay()) {
            $bills = $busy ? mt_rand(6, 10) : mt_rand(3, 5);
            $at = $day->setTime(8, 0);

            for ($n = 1; $n <= $bills; $n++) {
                $lines = [];

                for ($l = mt_rand(1, 4); $l > 0; $l--) {
                    $row = self::pick($menu, $total);
                    $lines[$row['item']->id] = ['row' => $row, 'quantity' => mt_rand(1, 3)];
                }

                $sequence++;
                $time = $at->addMinutes($n * 45)->utc();
                $now = now();
                $order = DB::table('pos_orders')->insertGetId([
                    'tenant_id' => $tenantId, 'property_id' => $propertyId, 'outlet_id' => $outletId, 'order_no' => 'H'.str_pad((string) $n, 3, '0', STR_PAD_LEFT), 'business_date' => $day->toDateString(), 'order_type' => 'dine_in',
                    'covers' => mt_rand(1, 4), 'waiter_id' => $waiter, 'status' => 'settled', 'subtotal' => 0, 'opened_at' => $time, 'closed_at' => $time, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $net = 0.0;
                $tax = 0.0;
                $billLines = [];

                foreach ($lines as $line) {
                    $amount = round((float) $line['row']['price'] * $line['quantity'], 2);
                    $lineTax = round($amount * 0.265, 2);
                    $orderLine = DB::table('pos_order_lines')->insertGetId([
                        'tenant_id' => $tenantId, 'property_id' => $propertyId, 'pos_order_id' => $order, 'menu_item_id' => $line['row']['item']->id, 'name_snapshot' => $line['row']['item']->translated('name'), 'quantity' => $line['quantity'],
                        'unit_price' => $line['row']['price'], 'modifier_total' => 0, 'line_total' => $amount, 'course' => 'main', 'status' => 'served', 'is_held' => false, 'created_at' => $now, 'updated_at' => $now,
                    ]);
                    $billLines[] = ['tenant_id' => $tenantId, 'property_id' => $propertyId, 'pos_order_line_id' => $orderLine, 'name_snapshot' => $line['row']['item']->translated('name'), 'quantity' => $line['quantity'], 'amount' => $amount, 'discount' => 0, 'tax' => $lineTax, 'gross' => $amount + $lineTax, 'created_at' => $now, 'updated_at' => $now];
                    $net += $amount;
                    $tax += $lineTax;
                }

                DB::table('pos_orders')->where('id', $order)->update(['subtotal' => round($net, 2)]);
                $bill = DB::table('pos_bills')->insertGetId([
                    'tenant_id' => $tenantId, 'property_id' => $propertyId, 'outlet_id' => $outletId, 'pos_order_id' => $order, 'sequence' => $sequence, 'bill_no' => $prefix.'-B'.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT),
                    'business_date' => $day->toDateString(), 'subtotal' => round($net, 2), 'discount_total' => 0, 'service_charge' => 0, 'tax_total' => round($tax, 2), 'grand_total' => round($net + $tax, 2), 'tip_total' => 0,
                    'paid_total' => round($net + $tax, 2), 'status' => 'settled', 'is_complimentary' => false, 'print_count' => 1, 'receipt_count' => 1, 'printed_at' => $time, 'settled_at' => $time, 'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('pos_bill_lines')->insert(array_map(fn (array $line): array => [...$line, 'pos_bill_id' => $bill], $billLines));
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $menu
     * @return array<string, mixed>
     */
    private static function pick(array $menu, int $total): array
    {
        $roll = mt_rand(1, $total);

        foreach ($menu as $row) {
            $roll -= $row['weight'];

            if ($roll <= 0) {
                return $row;
            }
        }

        return $menu[0];
    }
}
