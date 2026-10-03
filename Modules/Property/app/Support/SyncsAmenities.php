<?php

namespace Modules\Property\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Replaces a cottage type's or room type's amenities and records the change in its audit trail.
 */
trait SyncsAmenities
{
    /**
     * @param  list<int>  $amenityIds
     */
    protected function syncAmenities(Model&HasAmenities $subject, array $amenityIds): void
    {
        $relation = $subject->amenities();
        $old = $relation->pluck('name')->sort()->values()->all();

        $relation->syncWithPivotValues($amenityIds, ['tenant_id' => $subject->getAttribute('tenant_id')]);

        $new = $relation->pluck('name')->sort()->values()->all();

        if ($old !== $new) {
            activity()->performedOn($subject)->event('updated')
                ->withProperties(['old' => ['amenities' => implode(', ', $old)], 'attributes' => ['amenities' => implode(', ', $new)]])
                ->log('updated');
        }
    }
}
