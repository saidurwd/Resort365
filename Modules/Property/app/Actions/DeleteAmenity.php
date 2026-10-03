<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Models\Amenity;

/**
 * Removes an amenity from the catalogue and from every cottage type and room type.
 */
class DeleteAmenity extends Action
{
    public function handle(Amenity $amenity): void
    {
        $this->transaction(function () use ($amenity): void {
            $amenity->cottageTypes()->detach();
            $amenity->roomTypes()->detach();
            $amenity->delete();
        });
    }
}
