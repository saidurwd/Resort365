<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Models\Amenity;

/**
 * Creates or updates an amenity of the catalogue (validated by SaveAmenityRequest).
 */
class SaveAmenity extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Amenity $amenity, array $data): Amenity
    {
        $amenity ??= new Amenity;
        $amenity->fill($data)->save();

        return $amenity;
    }
}
