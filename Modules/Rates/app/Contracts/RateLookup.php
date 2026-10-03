<?php

namespace Modules\Rates\Contracts;

use Carbon\CarbonImmutable;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Rates\DTOs\DepositPolicySummary;
use Modules\Rates\DTOs\DepositQuote;
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

    /**
     * The deposit policy of a rate plan: its own, else the property's default (null = none set).
     */
    public function depositPolicy(int $ratePlanId): ?DepositPolicySummary;

    /**
     * The deposit of a booking under the plan's deposit policy (no policy = nothing due in advance).
     *
     * @param  string|null  $percent  negotiated percent, null = the policy's default
     */
    public function depositQuote(int $ratePlanId, string $grandTotal, string $firstNight, ?string $percent, CarbonImmutable $bookedAt, CarbonImmutable $arrivalAt): DepositQuote;

    /**
     * Whether the percent is within the plan's deposit policy limits (no override needed).
     */
    public function depositAllows(int $ratePlanId, string $percent): bool;

    /**
     * The cancellation policy of a rate plan: its own, else the property's default.
     */
    public function cancellationPolicyId(int $ratePlanId): ?int;

    /**
     * What cancelling costs under a cancellation policy (null = no policy, free): the fee, the
     * refund of what was paid above it and what is still owed (decimal strings).
     *
     * @param  list<string>  $nightlyTotals  the stay's nights in order
     * @param  int|null  $daysBeforeArrival  whole days from today to arrival; null for a no-show
     */
    public function cancellationQuote(?int $policyId, string $stayTotal, array $nightlyTotals, string $deposit, string $paid, ?int $daysBeforeArrival): CancellationQuote;

    /**
     * Counts one use of a promotion (a booking that got its discount).
     */
    public function usePromotion(int $promotionId): void;
}
