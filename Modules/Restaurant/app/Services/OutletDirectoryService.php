<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Contracts\OutletDirectory;
use Modules\Restaurant\DTOs\OutletListing;
use Modules\Restaurant\Models\Outlet;

class OutletDirectoryService implements OutletDirectory
{
    public function forProperty(int $propertyId): array
    {
        return Outlet::query()->where('property_id', $propertyId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get()
            ->map(fn (Outlet $outlet): OutletListing => new OutletListing($outlet->id, $outlet->name, $outlet->type, $outlet->opening_hours ?? []))
            ->values()->all();
    }
}
