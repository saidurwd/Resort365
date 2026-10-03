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
        );
    }
}
