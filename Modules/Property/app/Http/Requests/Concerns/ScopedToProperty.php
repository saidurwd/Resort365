<?php

namespace Modules\Property\Http\Requests\Concerns;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Model;

/**
 * For requests about property-level records: a new record belongs to the current property,
 * an existing one keeps its own.
 */
trait ScopedToProperty
{
    protected function propertyId(?Model $record): int
    {
        if ($record instanceof Model) {
            return (int) $record->getAttribute('property_id');
        }

        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    /**
     * Read switches as booleans; a switch that is not submitted at all stays on (forms always
     * send one, through a hidden "0").
     */
    protected function normalizeFlags(string ...$flags): void
    {
        foreach ($flags as $flag) {
            $this->merge([$flag => ! $this->has($flag) || $this->boolean($flag)]);
        }
    }
}
