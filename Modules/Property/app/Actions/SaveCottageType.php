<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Modules\Property\Models\CottageType;
use Modules\Property\Support\SyncsAmenities;

/**
 * Creates or updates a cottage type with its amenities (validated by SaveCottageTypeRequest).
 */
class SaveCottageType extends Action
{
    use SyncsAmenities;

    /**
     * @param  array<string, mixed>  $data  fields plus amenity_ids
     */
    public function handle(?CottageType $cottageType, array $data): CottageType
    {
        return $this->transaction(function () use ($cottageType, $data): CottageType {
            $cottageType ??= new CottageType;
            $cottageType->fill(Arr::except($data, 'amenity_ids'))->save();

            $this->syncAmenities($cottageType, array_map(intval(...), (array) ($data['amenity_ids'] ?? [])));

            return $cottageType;
        });
    }
}
