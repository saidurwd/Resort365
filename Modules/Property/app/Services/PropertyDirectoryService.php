<?php

namespace Modules\Property\Services;

use App\Support\Tenancy\PropertyContext;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;
use Modules\Property\Models\Property;

class PropertyDirectoryService implements PropertyDirectory
{
    public function find(int $id): ?PropertySummary
    {
        $property = Property::query()->find($id);

        return $property instanceof Property ? self::summary($property) : null;
    }

    public function current(): ?PropertySummary
    {
        $id = app(PropertyContext::class)->currentId();

        return $id !== null ? $this->find($id) : null;
    }

    public function all(): array
    {
        return Property::query()->orderBy('name')->get()->filter(fn (Property $property): bool => $property->isActive())
            ->map(fn (Property $property): PropertySummary => self::summary($property))->values()->all();
    }

    public static function summary(Property $property): PropertySummary
    {
        return new PropertySummary(
            $property->id,
            $property->code,
            $property->name,
            $property->timezone,
            $property->currency_code,
            substr($property->check_in_time, 0, 5),
            substr($property->check_out_time, 0, 5),
            $property->business_date->toDateString(),
            $property->isActive(),
            // One line for guest documents (vouchers, quotes, receipts).
            collect([$property->address_line1, $property->address_line2, $property->city])->filter()->implode(', ') ?: null,
            $property->phone,
            $property->email,
        );
    }
}
