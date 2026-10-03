<?php

namespace Modules\Core\Services;

use Modules\Core\Contracts\TaxEngine;
use Modules\Core\DTOs\TaxBreakdown;
use Modules\Core\DTOs\TaxCategorySummary;
use Modules\Core\DTOs\TaxRule;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;

class TaxEngineService implements TaxEngine
{
    public function __construct(private readonly TaxCalculator $calculator) {}

    public function categories(bool $activeOnly = true): array
    {
        return TaxCategory::query()->with('taxes')->when($activeOnly, fn ($query) => $query->where('is_active', true))->orderBy('name')->get()
            ->map(fn (TaxCategory $category): TaxCategorySummary => new TaxCategorySummary(
                $category->id, $category->code, $category->name,
                $category->taxes->where('is_active', true)->map(fn (Tax $tax): string => $tax->name)->values()->all(),
                $category->is_active,
            ))->values()->all();
    }

    public function calculate(string $amount, ?int $taxCategoryId, bool $inclusive = false, int $quantity = 1): TaxBreakdown
    {
        return $this->calculator->calculate($amount, $this->rules($taxCategoryId), $inclusive, $quantity);
    }

    /**
     * @return list<TaxRule>
     */
    private function rules(?int $taxCategoryId): array
    {
        if ($taxCategoryId === null) {
            return [];
        }

        $category = TaxCategory::query()->with('taxes')->find($taxCategoryId);

        return $category === null ? [] : $category->taxes->where('is_active', true)
            ->map(fn (Tax $tax): TaxRule => new TaxRule($tax->code, $tax->name, $tax->type, $tax->rate, $tax->is_compound))
            ->values()->all();
    }
}
