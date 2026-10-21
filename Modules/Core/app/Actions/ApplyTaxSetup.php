<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;

/**
 * Sets up a new company's taxes in one go (Step 7.4 onboarding): an optional service charge and an optional VAT (on the price, or
 * on the price plus service charge), and the categories Room, Food & beverage and Exempt that the rest of the system uses. A
 * rate of zero leaves that tax out. Refused once the company has tax categories, so it cannot double up.
 */
class ApplyTaxSetup extends Action
{
    /**
     * @return list<TaxCategory> the categories made
     *
     * @throws ValidationException
     */
    public function handle(string $serviceChargeRate, string $vatRate, bool $vatOnServiceCharge): array
    {
        if (TaxCategory::query()->exists()) {
            throw ValidationException::withMessages(['service_charge_rate' => __('Taxes are already set up. Change them in Setup → Taxes.')]);
        }

        foreach (['service_charge_rate' => $serviceChargeRate, 'vat_rate' => $vatRate] as $field => $rate) {
            if (! is_numeric($rate) || (float) $rate < 0 || (float) $rate > 100) {
                throw ValidationException::withMessages([$field => __('Enter a rate between 0 and 100.')]);
            }
        }

        return $this->transaction(function () use ($serviceChargeRate, $vatRate, $vatOnServiceCharge): array {
            $taxes = [];

            if ((float) $serviceChargeRate > 0) {
                $taxes[] = $this->tax('SC', 'Service charge', $serviceChargeRate, false, 10);
            }

            if ((float) $vatRate > 0) {
                $taxes[] = $this->tax('VAT', 'VAT', $vatRate, $vatOnServiceCharge && $taxes !== [], 20);
            }

            $ids = array_map(fn (Tax $tax): int => $tax->id, $taxes);

            return [
                SaveTaxCategory::make()->handle(null, ['code' => 'ROOM', 'name' => __('Room'), 'is_active' => true, 'tax_ids' => $ids]),
                SaveTaxCategory::make()->handle(null, ['code' => 'FNB', 'name' => __('Food & beverage'), 'is_active' => true, 'tax_ids' => $ids]),
                SaveTaxCategory::make()->handle(null, ['code' => 'EXEMPT', 'name' => __('Exempt'), 'is_active' => true, 'tax_ids' => []]),
            ];
        });
    }

    private function tax(string $code, string $name, string $rate, bool $compound, int $order): Tax
    {
        return SaveTax::make()->handle(null, ['code' => $code, 'name' => __($name), 'type' => 'percent', 'rate' => $rate, 'is_compound' => $compound, 'sort_order' => $order, 'is_active' => true]);
    }
}
