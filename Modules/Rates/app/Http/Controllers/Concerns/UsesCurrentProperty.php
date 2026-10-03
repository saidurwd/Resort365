<?php

namespace Modules\Rates\Http\Controllers\Concerns;

use App\Support\Tenancy\PropertyContext;
use Modules\Core\Contracts\Settings;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\Support\DaysOfWeek;

/**
 * Rates screens work on the property chosen in the navbar.
 */
trait UsesCurrentProperty
{
    protected function currentPropertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    protected function currentPropertyName(): string
    {
        return (string) app(PropertyContext::class)->currentName();
    }

    protected function currency(int $propertyId): string
    {
        return app(PropertyDirectory::class)->find($propertyId)->currencyCode ?? (string) app(Settings::class)->get('core.currency');
    }

    /**
     * @return list<int> ISO days of the property's weekend
     */
    protected function weekendDays(int $propertyId): array
    {
        return DaysOfWeek::parse((string) app(Settings::class)->get('rates.weekend_days', $propertyId));
    }
}
