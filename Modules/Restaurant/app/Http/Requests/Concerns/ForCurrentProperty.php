<?php

namespace Modules\Restaurant\Http\Requests\Concerns;

use App\Support\Tenancy\PropertyContext;
use Modules\Restaurant\Models\Outlet;

/**
 * Restaurant setup works on the current property (outlet screens: the outlet's property).
 */
trait ForCurrentProperty
{
    protected function propertyId(): int
    {
        $outlet = $this->route('outlet');

        if ($outlet instanceof Outlet) {
            return $outlet->property_id;
        }

        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    protected function outlet(): Outlet
    {
        $outlet = $this->route('outlet');

        return $outlet instanceof Outlet ? $outlet : abort(404);
    }
}
