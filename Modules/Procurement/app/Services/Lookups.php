<?php

namespace Modules\Procurement\Services;

use App\Support\Tenancy\PropertyContext;
use Modules\Core\Contracts\TaxEngine;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Procurement\Enums\VendorStatus;
use Modules\Procurement\Models\Vendor;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * The option lists of Procurement forms (id => label), read through the other modules' contracts.
 */
class Lookups
{
    public function __construct(
        private readonly ItemCatalog $items,
        private readonly StoreDirectory $stores,
        private readonly DepartmentDirectory $departments,
        private readonly TaxEngine $taxes,
        private readonly PropertyContext $property,
    ) {}

    /**
     * Stocked, active items as "SKU · name (unit)".
     *
     * @return array<int, string>
     */
    public function items(): array
    {
        $options = [];

        foreach ($this->items->search('', 500) as $item) {
            if ($item->isStockable) {
                $options[$item->id] = $item->sku.' · '.$item->name.' ('.$item->baseUnit.')';
            }
        }

        return $options;
    }

    /**
     * The current property's active stores.
     *
     * @return array<int, string>
     */
    public function stores(): array
    {
        $options = [];

        foreach ($this->stores->forProperty((int) $this->property->currentId()) as $store) {
            $options[$store->id] = $store->name;
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public function departments(): array
    {
        return collect($this->departments->all())->mapWithKeys(fn ($department): array => [$department->id => $department->name])->all();
    }

    /**
     * @return array<int, string>
     */
    public function taxCategories(): array
    {
        return collect($this->taxes->categories())->mapWithKeys(fn ($category): array => [$category->id => $category->name])->all();
    }

    /**
     * @return array<int, string>
     */
    public function vendors(bool $activeOnly = true): array
    {
        return Vendor::query()->when($activeOnly, fn ($query) => $query->where('status', VendorStatus::Active->value))->orderBy('name')->get()->mapWithKeys(fn (Vendor $vendor): array => [$vendor->id => $vendor->name])->all();
    }
}
