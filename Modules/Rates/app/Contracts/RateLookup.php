<?php

namespace Modules\Rates\Contracts;

use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\NightlyRate;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Rates\DTOs\StayRequest;

/**
 * Rates for other modules (Reservation): rate plans, nightly rates, restrictions and promotions.
 * Unit keys are "room_type:{id}" or "cottage_type:{id}". Lookups respect the user's property access.
 */
interface RateLookup
{
    /**
     * @return list<RatePlanSummary>
     */
    public function ratePlans(int $propertyId, bool $activeOnly = true): array;

    public function ratePlan(int $ratePlanId): ?RatePlanSummary;

    /**
     * Price of each night in [from, to] (both included) per unit key; null = no price set.
     *
     * @param  list<string>  $unitKeys
     * @return array<string, array<string, NightlyRate|null>>
     */
    public function nightlyRates(int $ratePlanId, array $unitKeys, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Restrictions in force per unit key and date in [from, to]; dates without any are missing.
     *
     * @param  list<string>  $unitKeys
     * @return array<string, array<string, RestrictionSet>>
     */
    public function restrictions(int $ratePlanId, array $unitKeys, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * The best promotion of the property for a stay (code promotions only with their code).
     */
    public function bestPromotion(int $propertyId, StayRequest $stay): ?PromotionDiscount;
}
