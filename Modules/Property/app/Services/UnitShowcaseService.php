<?php

namespace Modules\Property\Services;

use App\Support\Attachments\Attachments;
use Modules\Property\Contracts\UnitShowcases;
use Modules\Property\DTOs\UnitShowcase;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Enums\CottageStatus;
use Modules\Property\Enums\UnitKind;
use Modules\Property\Models\Amenity;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\RoomType;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class UnitShowcaseService implements UnitShowcases
{
    public function forProperty(int $propertyId): array
    {
        $roomTypes = RoomType::query()->where('property_id', $propertyId)->where('is_active', true)
            ->withCount(['rooms' => fn ($rooms) => $rooms->where('is_active', true)])
            ->with(['amenities' => fn ($amenities) => $amenities->where('is_active', true), 'media'])
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn (RoomType $type): bool => $type->rooms_count > 0)
            ->map(fn (RoomType $type): UnitShowcase => new UnitShowcase(
                UnitKind::RoomType, $type->id, $type->code, $type->name, $type->description, $type->max_occupancy, $type->max_adults, $type->max_children,
                $type->bed_configuration, $type->size_sqm, 1, (int) $type->rooms_count, $this->amenities($type->amenities->all()), $this->photos($type->media->all()),
            ));

        $cottageTypes = CottageType::query()->where('property_id', $propertyId)->where('is_active', true)
            ->with(['amenities' => fn ($amenities) => $amenities->where('is_active', true), 'media',
                'cottages' => fn ($cottages) => $cottages->where('status', CottageStatus::Active->value)->whereIn('booking_mode', [BookingMode::WholeOnly->value, BookingMode::Both->value])->with('media')])
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn (CottageType $type): bool => $type->cottages->isNotEmpty())
            ->map(fn (CottageType $type): UnitShowcase => new UnitShowcase(
                UnitKind::CottageType, $type->id, $type->code, $type->name, $type->description, $type->max_occupancy, null, null, null, null, $type->bedrooms,
                $type->cottages->count(), $this->amenities($type->amenities->all()),
                [...$this->photos($type->media->all()), ...$type->cottages->flatMap(fn (Cottage $cottage): array => $this->photos($cottage->media->all()))->all()],
            ));

        return [...$roomTypes->values()->all(), ...$cottageTypes->values()->all()];
    }

    /**
     * @param  list<Amenity>  $amenities
     * @return list<array{name: string, icon: string|null}>
     */
    private function amenities(array $amenities): array
    {
        return array_map(fn (Amenity $amenity): array => ['name' => $amenity->name, 'icon' => $amenity->icon], $amenities);
    }

    /**
     * @param  list<Media>  $media
     * @return list<int>
     */
    private function photos(array $media): array
    {
        return array_values(array_map(fn (Media $item): int => (int) $item->id, array_filter($media, fn (Media $item): bool => $item->collection_name === Attachments::PHOTOS)));
    }
}
