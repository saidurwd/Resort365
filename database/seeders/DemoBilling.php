<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Modules\Billing\Actions\PostCharge;
use Modules\Billing\Actions\SaveExtraService;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\ExtraService;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Models\TaxCategory;
use Modules\Guest\Models\Company;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\Reservation;

/**
 * Billing demo data (Step 2.1): the default charge codes per tenant (room and F&B codes taxed),
 * an extras catalogue for a property, and an airport pickup on the confirmed booking's folio.
 */
final class DemoBilling
{
    /**
     * name => [charge code, price, unit, price includes tax]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: bool}>
     */
    private const array EXTRAS = [
        'Airport pickup' => ['TRANSFER', '1500.00', 'trip', true],
        'Extra bed' => ['EXBED', '1200.00', 'night', false],
        'Laundry' => ['LAUNDRY', '60.00', 'piece', true],
        'Spa massage (60 min)' => ['SPA', '3500.00', 'person', true],
        'Beach BBQ dinner' => ['FNB', '2200.00', 'person', false],
        'Sunset boat trip' => ['MISC', '1000.00', 'person', true],
    ];

    public static function chargeCodes(Tenant $tenant): void
    {
        app(TenantContext::class)->run($tenant, function (): void {
            app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id'));
            ChargeCode::query()->where('code', 'FNB')->update(['tax_category_id' => TaxCategory::query()->where('code', 'FNB')->value('id')]);
        });
    }

    public static function extras(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            if (ExtraService::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $codes = ChargeCode::query()->pluck('id', 'code');
            $order = 0;

            foreach (self::EXTRAS as $name => [$code, $price, $unit, $inclusive]) {
                SaveExtraService::make()->handle(null, ['property_id' => $propertyId, 'charge_code_id' => $codes[$code], 'name' => $name, 'unit' => $unit,
                    'unit_price' => $price, 'price_includes_tax' => $inclusive, 'is_active' => true, 'sort_order' => $order += 10]);
            }

            $confirmed = Reservation::query()->where('property_id', $propertyId)->where('status', ReservationStatus::Confirmed->value)->orderBy('id')->first();
            $pickup = ExtraService::query()->where('property_id', $propertyId)->where('name', 'Airport pickup')->first();

            if ($confirmed instanceof Reservation && $pickup instanceof ExtraService) {
                PostCharge::make()->handle(new FolioCharge($confirmed->id, $pickup->charge_code_id, $pickup->unit_price, description: 'Airport pickup on arrival (11:00)',
                    priceIncludesTax: true, extraServiceId: $pickup->id));
            }
        });
    }

    /**
     * Step 2.3: a company that still owes for a stay 40 days ago (due 10 days ago), for the city
     * ledger's aging.
     */
    public static function cityLedger(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            $company = Company::query()->orderBy('id')->first();

            if (! $company instanceof Company || CityLedgerEntry::query()->exists()) {
                return;
            }

            $posted = CarbonImmutable::today()->subDays(40);
            CityLedgerEntry::query()->create([
                'property_id' => $propertyId, 'company_id' => $company->id, 'posted_on' => $posted->toDateString(),
                'due_on' => $posted->addDays($company->payment_terms_days ?: 30)->toDateString(),
                'description' => 'Conference stay, 3 rooms × 2 nights', 'amount' => '45540.00', 'status' => CityLedgerStatus::Open,
            ]);
        });
    }
}
