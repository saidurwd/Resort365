<?php

namespace Modules\Property\Http\Controllers\Concerns;

use App\Support\Tenancy\PropertyContext;

/**
 * Setup screens for cottages, rooms and their types work on the property chosen in the navbar.
 */
trait UsesCurrentProperty
{
    protected function currentPropertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    protected function currentPropertyName(): string
    {
        return (string) app(PropertyContext::class)->currentName();
    }
}
