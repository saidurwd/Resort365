<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Core\Contracts\ReferenceData;
use Modules\Core\Models\Country;
use Modules\Core\Models\Currency;
use Modules\Core\Models\Timezone;

/**
 * Cached for a day: reference data only changes when ReferenceDataSeeder runs.
 */
class ReferenceDataService implements ReferenceData
{
    public function countries(): array
    {
        /** @var array<string, string> */
        return Cache::remember('core.reference.countries', now()->addDay(), fn (): array => Country::query()->orderBy('name')->pluck('name', 'code')->all());
    }

    public function currencies(): array
    {
        /** @var array<string, string> */
        return Cache::remember('core.reference.currencies', now()->addDay(), fn (): array => Currency::query()->orderBy('code')->get()
            ->mapWithKeys(fn (Currency $currency): array => [(string) $currency->getKey() => $currency->getKey().' — '.$currency->getAttribute('name')])->all());
    }

    public function timezones(): array
    {
        /** @var array<string, string> */
        return Cache::remember('core.reference.timezones', now()->addDay(), fn (): array => Timezone::query()->orderBy('name')->get()
            ->mapWithKeys(fn (Timezone $timezone): array => [(string) $timezone->getKey() => $timezone->getKey().' (UTC'.$timezone->getAttribute('utc_offset').')'])->all());
    }
}
