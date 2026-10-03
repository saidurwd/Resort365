<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\TaxBreakdown;
use Modules\Core\DTOs\TaxCategorySummary;

/**
 * Taxes for other modules (Rates, Billing, Restaurant). They store a tax category id and ask
 * for the breakdown; amounts are decimal strings.
 */
interface TaxEngine
{
    /**
     * The current tenant's tax categories, by name.
     *
     * @return list<TaxCategorySummary>
     */
    public function categories(bool $activeOnly = true): array;

    /**
     * Applies the category's active taxes. No category (null) means no tax.
     *
     * @param  bool  $inclusive  whether $amount already includes the taxes
     * @param  int  $quantity  units for fixed taxes (e.g. nights)
     */
    public function calculate(string $amount, ?int $taxCategoryId, bool $inclusive = false, int $quantity = 1): TaxBreakdown;
}
