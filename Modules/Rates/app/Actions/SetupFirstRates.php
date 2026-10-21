<?php

namespace Modules\Rates\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\Core\Contracts\Settings;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\DTOs\TaxCategorySummary;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Support\DaysOfWeek;

/**
 * The prices step of the setup checklist (Step 7.4): a "Room only" rate plan with one nightly price per room and cottage type of the
 * property, taxed with the ROOM tax category when there is one, plus the default deposit policy (30% advance) and the Flexible
 * cancellation policy when the property has none. Only for a property with no rate plans yet; seasons, weekend prices and other
 * plans are added under Rates afterwards.
 */
class SetupFirstRates extends Action
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly TaxEngine $taxes,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  array<string, string|null>  $prices  unit key ("room_type:4") => price per night; an empty price leaves the type unpriced
     *
     * @throws ValidationException
     */
    public function handle(int $propertyId, array $prices): RatePlan
    {
        if (RatePlan::query()->where('property_id', $propertyId)->exists()) {
            throw ValidationException::withMessages(['prices' => __('This property already has rate plans. Change them under Rates.')]);
        }

        $units = $this->catalog->unitTypes($propertyId, activeOnly: true);
        $rows = [];

        foreach ($units as $unit) {
            $price = trim((string) ($prices[$unit->key()] ?? ''));

            if ($price !== '') {
                $rows[$unit->key()] = ['every' => ['amount' => $price]];
            }
        }

        if ($rows === []) {
            throw ValidationException::withMessages(['prices' => __('Enter a price for at least one kind of room.')]);
        }

        $roomTax = collect($this->taxes->categories())->first(fn (TaxCategorySummary $category): bool => $category->code === 'ROOM');

        return $this->transaction(function () use ($propertyId, $rows, $roomTax): RatePlan {
            $this->policies($propertyId);

            $plan = SaveRatePlan::make()->handle(null, [
                'property_id' => $propertyId, 'code' => 'RO', 'name' => __('Room only'), 'meal_plan' => 'EP', 'meal_adult_amount' => '0',
                'meal_child_amount' => '0', 'is_refundable' => true, 'prices_include_tax' => false, 'tax_category_id' => $roomTax?->id,
                'channels' => ['front_desk', 'online'], 'is_active' => true, 'sort_order' => 0,
            ]);
            // The weekend set is left empty: one price for every night.
            SaveRateSheet::make()->handle($plan, null, $rows, DaysOfWeek::mask(DaysOfWeek::parse((string) $this->settings->get('rates.weekend_days', $propertyId)) ?: [5, 6]));

            return $plan;
        });
    }

    private function policies(int $propertyId): void
    {
        if (! DepositPolicy::query()->where('property_id', $propertyId)->exists()) {
            SaveDepositPolicy::make()->handle(null, [
                'property_id' => $propertyId, 'name' => __('Standard advance'), 'type' => 'percentage', 'min_percent' => '20', 'default_percent' => '30',
                'max_percent' => null, 'due_within_minutes' => 30, 'auto_cancel_unpaid' => true, 'balance_due_rule' => 'at_check_in',
                'full_payment_within_hours' => 24, 'is_default' => true,
            ]);
        }

        if (! CancellationPolicy::query()->where('property_id', $propertyId)->exists()) {
            SaveCancellationPolicy::make()->handle(null, [
                'property_id' => $propertyId, 'name' => __('Flexible'), 'is_default' => true,
                'description' => __('Free cancellation until 15 days before arrival; half the deposit is kept 7–14 days before; the deposit is kept after that and for no-shows.'),
                'no_show_charge_type' => 'percent_of_deposit', 'no_show_charge_value' => '100',
                'rules' => [
                    ['days_before_from' => 15, 'days_before_to' => null, 'charge_type' => 'percent_of_deposit', 'charge_value' => '0'],
                    ['days_before_from' => 7, 'days_before_to' => 14, 'charge_type' => 'percent_of_deposit', 'charge_value' => '50'],
                    ['days_before_from' => 0, 'days_before_to' => 6, 'charge_type' => 'percent_of_deposit', 'charge_value' => '100'],
                ],
            ]);
        }
    }
}
