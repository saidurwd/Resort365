<?php

namespace Modules\Property\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Modules\Property\Models\Amenity;

/**
 * A record with amenities from the catalogue (cottage types and room types), linked through amenity_links.
 */
interface HasAmenities
{
    /**
     * @return MorphToMany<Amenity, covariant Model>
     */
    public function amenities(): MorphToMany;
}
