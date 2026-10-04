<?php

namespace Modules\Housekeeping\Http\Requests\Concerns;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantRule;
use Illuminate\Validation\Rules\Exists;

/**
 * Housekeeping screens work on the current property: rooms must be its rooms.
 */
trait ForCurrentProperty
{
    protected function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    protected function roomRule(): Exists
    {
        return TenantRule::exists('rooms')->where('property_id', $this->propertyId())->withoutTrashed();
    }
}
