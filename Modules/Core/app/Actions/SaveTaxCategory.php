<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;

/**
 * Creates or updates a tax category and its taxes (validated by SaveTaxCategoryRequest).
 * Changes to the taxes are recorded in the category's audit trail.
 */
class SaveTaxCategory extends Action
{
    /**
     * @param  array<string, mixed>  $data  fields plus tax_ids
     */
    public function handle(?TaxCategory $category, array $data): TaxCategory
    {
        return $this->transaction(function () use ($category, $data): TaxCategory {
            $category ??= new TaxCategory;
            $category->fill(Arr::except($data, 'tax_ids'))->save();

            $old = $category->taxes()->pluck('name')->all();
            $category->taxes()->syncWithPivotValues(array_map(intval(...), (array) ($data['tax_ids'] ?? [])), ['tenant_id' => $category->tenant_id]);
            $new = $category->taxes()->get()->map(fn (Tax $tax): string => $tax->name)->all();

            if ($old !== $new) {
                activity()->performedOn($category)->event('updated')
                    ->withProperties(['old' => ['taxes' => implode(', ', $old)], 'attributes' => ['taxes' => implode(', ', $new)]])
                    ->log('updated');
            }

            return $category;
        });
    }
}
