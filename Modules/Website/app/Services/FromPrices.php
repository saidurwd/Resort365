<?php

namespace Modules\Website\Services;

use App\Support\Tenancy\TenantCache;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\DTOs\RatePlanSummary;

/**
 * The "from" price of each room type and cottage type on the website (Step 8.2a): the lowest nightly rate of a plan sold online over
 * the coming days (config website.from_price_days), for the base occupancy. Kept ten minutes per property, as plain arrays.
 */
class FromPrices
{
    public function __construct(private readonly RateLookup $rates, private readonly TenantCache $cache) {}

    /**
     * @param  list<string>  $unitKeys  e.g. `room_type:4`
     * @return array<string, array{amount: string, plus_tax: bool}> unit key => lowest nightly rate; types with no rate are missing
     */
    public function forUnits(int $propertyId, array $unitKeys): array
    {
        if ($unitKeys === []) {
            return [];
        }

        sort($unitKeys);
        $key = 'website:from-prices:'.$propertyId.':'.md5(implode(',', $unitKeys));

        /** @var array<string, array{amount: string, plus_tax: bool}> */
        return $this->cache->remember($key, 600, fn (): array => $this->lowest($propertyId, $unitKeys));
    }

    /**
     * @param  list<string>  $unitKeys
     * @return array<string, array{amount: string, plus_tax: bool}>
     */
    private function lowest(int $propertyId, array $unitKeys): array
    {
        $from = CarbonImmutable::today();
        $to = $from->addDays(max(1, (int) config('website.from_price_days', 90)));
        $lowest = [];

        $plans = array_filter($this->rates->ratePlans($propertyId), fn (RatePlanSummary $plan): bool => $plan->sellsThrough('online'));

        foreach ($plans as $plan) {
            foreach ($this->rates->nightlyRates($plan->id, $unitKeys, $from, $to) as $unitKey => $nights) {
                foreach ($nights as $night) {
                    if (! $night instanceof NightlyRate || ! $plan->isValidOn($night->date) || BigDecimal::of($night->amount)->isLessThanOrEqualTo(0)) {
                        continue;
                    }

                    if (! isset($lowest[$unitKey]) || BigDecimal::of($night->amount)->isLessThan($lowest[$unitKey]['amount'])) {
                        $lowest[$unitKey] = ['amount' => (string) BigDecimal::of($night->amount)->toScale(2), 'plus_tax' => ! $plan->pricesIncludeTax];
                    }
                }
            }
        }

        return $lowest;
    }
}
