<?php

namespace Modules\Property\Services;

use Carbon\CarbonImmutable;
use Modules\Property\Contracts\BusinessDates;
use Modules\Property\Exceptions\BusinessDateMismatch;
use Modules\Property\Models\Property;

class BusinessDatesService implements BusinessDates
{
    public function advance(int $propertyId, string $from): string
    {
        $next = CarbonImmutable::parse($from)->addDay()->toDateString();
        $changed = Property::query()->whereKey($propertyId)->where('business_date', $from)->update(['business_date' => $next]);

        if ($changed !== 1) {
            throw new BusinessDateMismatch(__('The business date is no longer :date.', ['date' => CarbonImmutable::parse($from)->format('d M Y')]));
        }

        return $next;
    }
}
