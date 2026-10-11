<?php

namespace Modules\Inventory\Services;

use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Inventory\DTOs\StoreSummary;
use Modules\Inventory\Models\Store;

class StoreDirectoryService implements StoreDirectory
{
    public function find(int $storeId): ?StoreSummary
    {
        $store = Store::query()->find($storeId);

        return $store instanceof Store ? $this->summary($store) : null;
    }

    public function forProperty(int $propertyId, bool $activeOnly = true): array
    {
        return Store::query()->where('property_id', $propertyId)->when($activeOnly, fn ($query) => $query->where('is_active', true))->orderBy('name')->get()
            ->map(fn (Store $store): StoreSummary => $this->summary($store))->all();
    }

    private function summary(Store $store): StoreSummary
    {
        return new StoreSummary($store->id, $store->property_id, $store->code, $store->name, $store->kind, $store->department_id, $store->is_active);
    }
}
