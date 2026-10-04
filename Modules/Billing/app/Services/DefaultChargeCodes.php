<?php

namespace Modules\Billing\Services;

use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Models\ChargeCode;

/**
 * The charge codes every tenant starts with (editable at Setup → Charge codes). Missing ones are
 * created; existing codes are left as the tenant changed them. Runs inside TenantContext.
 */
class DefaultChargeCodes
{
    /**
     * @var list<array{0: string, 1: string, 2: ChargeCategory}>
     */
    public const array CODES = [
        ['ROOM', 'Room charge', ChargeCategory::Room],
        ['EXBED', 'Extra bed', ChargeCategory::Room],
        ['FNB', 'Food & beverage', ChargeCategory::FoodBeverage],
        ['TRANSFER', 'Airport transfer', ChargeCategory::Service],
        ['LAUNDRY', 'Laundry', ChargeCategory::Service],
        ['SPA', 'Spa', ChargeCategory::Extra],
        ['MISC', 'Miscellaneous', ChargeCategory::Miscellaneous],
    ];

    /**
     * @param  int|null  $taxCategoryId  tax category for the new codes (null = untaxed)
     */
    public function ensure(?int $taxCategoryId = null): void
    {
        foreach (self::CODES as $order => [$code, $name, $category]) {
            if (! ChargeCode::withTrashed()->where('code', $code)->exists()) {
                ChargeCode::query()->create(['code' => $code, 'name' => $name, 'category' => $category, 'tax_category_id' => $taxCategoryId, 'sort_order' => $order * 10]);
            }
        }
    }
}
