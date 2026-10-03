<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Modules\Property\Models\RoomType;
use Modules\Property\Support\SyncsAmenities;

/**
 * Creates or updates a room type with its amenities (validated by SaveRoomTypeRequest).
 */
class SaveRoomType extends Action
{
    use SyncsAmenities;

    /**
     * @param  array<string, mixed>  $data  fields plus amenity_ids
     */
    public function handle(?RoomType $roomType, array $data): RoomType
    {
        return $this->transaction(function () use ($roomType, $data): RoomType {
            $roomType ??= new RoomType;
            $roomType->fill(Arr::except($data, 'amenity_ids'))->save();

            $this->syncAmenities($roomType, array_map(intval(...), (array) ($data['amenity_ids'] ?? [])));

            return $roomType;
        });
    }
}
