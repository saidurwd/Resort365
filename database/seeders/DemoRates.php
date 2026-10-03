<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Modules\Core\Actions\SaveTax;
use Modules\Core\Actions\SaveTaxCategory;
use Modules\Core\Models\TaxCategory;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Rates\Actions\SaveRatePlan;
use Modules\Rates\Actions\SaveRateSheet;
use Modules\Rates\Actions\SaveSeason;
use Modules\Rates\Actions\SetRateOverrides;
use Modules\Rates\Actions\SetRestrictions;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Models\Season;
use Modules\Rates\Support\DaysOfWeek;

/**
 * Taxes and rates for DemoSeeder (Step 1.3): service charge 10% then VAT 15% (compound) on rooms
 * and food; seasons, rate plans and rates per property, with weekend uplifts, a New Year's Eve
 * date price, a minimum stay over New Year and one stop-sell date.
 */
final class DemoRates
{
    private const int WEEKEND = 48; // Friday + Saturday

    /**
     * Room Only base price per room or cottage type code, in BDT.
     */
    private const array BASE_PRICES = [
        'HS' => 9000, 'DK' => 6000, 'TW' => 5500, 'FS' => 9500,
        'HC' => 9000, 'GC' => 11000, 'FV' => 25000, 'TB' => 10000, 'HV' => 16000, 'LC' => 5500, 'FL' => 10500, 'FAM' => 20000,
    ];

    public static function taxes(Tenant $tenant): void
    {
        app(TenantContext::class)->run($tenant, function (): void {
            if (TaxCategory::query()->exists()) {
                return;
            }

            $serviceCharge = SaveTax::make()->handle(null, ['code' => 'SC', 'name' => 'Service charge', 'type' => 'percent', 'rate' => '10', 'is_compound' => false, 'sort_order' => 10, 'is_active' => true]);
            $vat = SaveTax::make()->handle(null, ['code' => 'VAT', 'name' => 'VAT', 'type' => 'percent', 'rate' => '15', 'is_compound' => true, 'sort_order' => 20, 'is_active' => true,
                'description' => 'Charged on the price plus service charge.']);

            SaveTaxCategory::make()->handle(null, ['code' => 'ROOM', 'name' => 'Room', 'is_active' => true, 'tax_ids' => [$serviceCharge->id, $vat->id]]);
            SaveTaxCategory::make()->handle(null, ['code' => 'FNB', 'name' => 'Food & beverage', 'is_active' => true, 'tax_ids' => [$serviceCharge->id, $vat->id]]);
            SaveTaxCategory::make()->handle(null, ['code' => 'EXEMPT', 'name' => 'Exempt', 'is_active' => true, 'tax_ids' => []]);
        });
    }

    /**
     * @param  bool  $full  Cox's Bazar gets every season, plan and special; the others a simpler set
     */
    public static function rates(Tenant $tenant, int $propertyId, bool $full): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $full): void {
            if (RatePlan::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $year = CarbonImmutable::now()->year;
            $roomTax = TaxCategory::query()->where('code', 'ROOM')->value('id');
            $units = app(InventoryCatalog::class)->unitTypes($propertyId);

            $season = fn (string $name, string $color, int $priority, array $periods): Season => SaveSeason::make()->handle(null, [
                'property_id' => $propertyId, 'name' => $name, 'color' => $color, 'priority' => $priority, 'is_active' => true,
                'periods' => array_map(fn (array $period): array => ['start_date' => $period[0], 'end_date' => $period[1]], $periods),
            ]);

            $seasons = [
                [$season('Peak', 'primary', 30, [["{$year}-12-15", ($year + 1).'-01-31'], [($year - 1).'-12-15', "{$year}-01-31"]]), '1.40'],
                [$season('Shoulder', 'warning', 10, [["{$year}-10-01", "{$year}-11-30"], [($year + 1).'-02-01', ($year + 1).'-03-31'], ["{$year}-02-01", "{$year}-03-31"]]), '1.15'],
            ];

            if ($full) {
                $seasons[] = [$season('Monsoon low', 'info', 20, [["{$year}-06-01", "{$year}-08-31"], [($year + 1).'-06-01', ($year + 1).'-08-31']]), '0.80'];
            }

            $plans = [['RO', 'Room Only', 'EP', '0', '0', '0']];
            $plans[] = ['BB', 'Bed & Breakfast', 'CP', '800', '400', '1200'];

            if ($full) {
                $plans[] = ['HB', 'Half Board', 'MAP', '2000', '1000', '3000'];
            }

            foreach ($plans as $order => [$code, $name, $meal, $adult, $child, $supplement]) {
                $plan = SaveRatePlan::make()->handle(null, [
                    'property_id' => $propertyId, 'code' => $code, 'name' => $name, 'meal_plan' => $meal, 'meal_adult_amount' => $adult,
                    'meal_child_amount' => $child, 'is_refundable' => true, 'prices_include_tax' => false, 'tax_category_id' => $roomTax,
                    'channels' => ['front_desk', 'online'], 'is_active' => true, 'sort_order' => $order,
                ]);

                SaveRateSheet::make()->handle($plan, null, self::sheet($units, '1', $supplement), self::WEEKEND);

                foreach ($seasons as [$item, $factor]) {
                    SaveRateSheet::make()->handle($plan, $item, self::sheet($units, $factor, $supplement), self::WEEKEND);
                }

                if ($full && $code === 'RO') {
                    $newYearsEve = CarbonImmutable::parse("{$year}-12-31");
                    $special = array_values(array_filter($units, fn (UnitTypeSummary $unit): bool => isset(self::BASE_PRICES[$unit->code])));

                    foreach ($special as $unit) {
                        SetRateOverrides::make()->handle($plan, [$unit], $newYearsEve, $newYearsEve, DaysOfWeek::EVERY_DAY, self::price(self::BASE_PRICES[$unit->code], '1.80', '0'));
                    }

                    $villa = array_values(array_filter($units, fn (UnitTypeSummary $unit): bool => $unit->code === 'FV'));
                    $stop = CarbonImmutable::now()->addMonth()->startOfMonth()->addDays(14);
                    SetRestrictions::make()->handle($propertyId, null, $villa, $stop, $stop, DaysOfWeek::EVERY_DAY, new RestrictionSet(stopSell: true));
                }
            }

            if ($full) {
                SetRestrictions::make()->handle($propertyId, null, null, CarbonImmutable::parse("{$year}-12-30"), CarbonImmutable::parse(($year + 1).'-01-01'),
                    DaysOfWeek::EVERY_DAY, new RestrictionSet(minStay: 2));
            }
        });
    }

    /**
     * @param  list<UnitTypeSummary>  $units
     * @return array<string, array<string, array<string, string>>>
     */
    private static function sheet(array $units, string $factor, string $supplement): array
    {
        $rows = [];

        foreach ($units as $unit) {
            $base = self::BASE_PRICES[$unit->code] ?? 6000;
            $rows[$unit->key()] = [
                'every' => ['amount' => self::price($base, $factor, $supplement), 'extra_adult_amount' => '1500', 'extra_child_amount' => '750'],
                'weekend' => ['amount' => self::price($base, bcmul($factor, '1.15', 4), $supplement), 'extra_adult_amount' => '1500', 'extra_child_amount' => '750'],
            ];
        }

        return $rows;
    }

    /**
     * base × factor + supplement, rounded to the nearest 100.
     */
    private static function price(int $base, string $factor, string $supplement): string
    {
        return (string) BigDecimal::of($base)->multipliedBy($factor)->plus($supplement)
            ->dividedBy(100, 0, RoundingMode::HalfUp)->multipliedBy(100)->toScale(2);
    }
}
