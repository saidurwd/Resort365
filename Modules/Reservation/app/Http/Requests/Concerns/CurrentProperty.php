<?php

namespace Modules\Reservation\Http\Requests\Concerns;

use App\Support\Tenancy\PropertyContext;

/**
 * Booking screens work on the property chosen in the navbar.
 */
trait CurrentProperty
{
    public function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
