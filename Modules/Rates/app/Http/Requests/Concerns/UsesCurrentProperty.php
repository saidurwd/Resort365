<?php

namespace Modules\Rates\Http\Requests\Concerns;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Database\Eloquent\Model;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\UnitTypeSummary;

/**
 * Rates screens work on the property chosen in the navbar; an existing record keeps its own.
 */
trait UsesCurrentProperty
{
    protected function propertyId(?Model $record = null): int
    {
        if ($record instanceof Model) {
            return (int) $record->getAttribute('property_id');
        }

        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    /**
     * The property's room and cottage types by key ("room_type:4").
     *
     * @return array<string, UnitTypeSummary>
     */
    protected function unitTypes(int $propertyId): array
    {
        $units = [];

        foreach (app(InventoryCatalog::class)->unitTypes($propertyId) as $unit) {
            $units[$unit->key()] = $unit;
        }

        return $units;
    }
}
