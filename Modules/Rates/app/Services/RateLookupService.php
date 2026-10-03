<?php

namespace Modules\Rates\Services;

use Carbon\CarbonImmutable;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Rates\DTOs\CancellationTerms;
use Modules\Rates\DTOs\DepositPolicySummary;
use Modules\Rates\DTOs\DepositQuote;
use Modules\Rates\DTOs\DepositTerms;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Rates\DTOs\PromotionTerms;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Rates\DTOs\StayRequest;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\DepositType;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RatePlan;

class RateLookupService implements RateLookup
{
    public function __construct(
        private readonly RateCalendar $calendar,
        private readonly PromotionMatcher $promotions,
        private readonly DepositCalculator $deposits,
        private readonly CancellationFeeCalculator $cancellations,
    ) {}

    public function ratePlans(int $propertyId, bool $activeOnly = true): array
    {
        return RatePlan::query()->where('property_id', $propertyId)->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->orderBy('sort_order')->orderBy('name')->get()->map(fn (RatePlan $plan): RatePlanSummary => $this->summary($plan))->values()->all();
    }

    public function ratePlan(int $ratePlanId): ?RatePlanSummary
    {
        $plan = RatePlan::query()->find($ratePlanId);

        return $plan instanceof RatePlan ? $this->summary($plan) : null;
    }

    public function nightlyRates(int $ratePlanId, array $unitKeys, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $plan = RatePlan::query()->find($ratePlanId);

        return $plan instanceof RatePlan ? $this->calendar->rates($plan, $unitKeys, $from, $to) : [];
    }

    public function restrictions(int $ratePlanId, array $unitKeys, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $plan = RatePlan::query()->find($ratePlanId);

        return $plan instanceof RatePlan ? $this->calendar->restrictions($plan, $unitKeys, $from, $to) : [];
    }

    public function bestPromotion(int $propertyId, StayRequest $stay): ?PromotionDiscount
    {
        $terms = Promotion::query()->where('property_id', $propertyId)->where('is_active', true)->get()
            ->map(fn (Promotion $promotion): PromotionTerms => $promotion->terms())->values()->all();

        return $this->promotions->best($terms, $stay);
    }

    public function depositPolicy(int $ratePlanId): ?DepositPolicySummary
    {
        $plan = RatePlan::query()->find($ratePlanId);

        if (! $plan instanceof RatePlan) {
            return null;
        }

        $policy = ($plan->deposit_policy_id !== null ? DepositPolicy::query()->find($plan->deposit_policy_id) : null)
            ?? DepositPolicy::query()->where('property_id', $plan->property_id)->where('is_default', true)->first();

        return $policy instanceof DepositPolicy ? new DepositPolicySummary($policy->id, $policy->name, $policy->terms()) : null;
    }

    public function depositQuote(int $ratePlanId, string $grandTotal, string $firstNight, ?string $percent, CarbonImmutable $bookedAt, CarbonImmutable $arrivalAt): DepositQuote
    {
        $terms = $this->depositPolicy($ratePlanId)->terms
            ?? new DepositTerms(DepositType::None, null, '0', null, null, 0, false, BalanceDueRule::AtCheckIn);

        return $this->deposits->quote($terms, $grandTotal, $firstNight, $percent, $bookedAt, $arrivalAt);
    }

    public function depositAllows(int $ratePlanId, string $percent): bool
    {
        $policy = $this->depositPolicy($ratePlanId);

        return ! $policy instanceof DepositPolicySummary || $this->deposits->isWithinLimits($policy->terms, $percent);
    }

    public function cancellationPolicyId(int $ratePlanId): ?int
    {
        $plan = RatePlan::query()->find($ratePlanId);

        if (! $plan instanceof RatePlan) {
            return null;
        }

        $id = $plan->cancellation_policy_id
            ?? CancellationPolicy::query()->where('property_id', $plan->property_id)->where('is_default', true)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function cancellationQuote(?int $policyId, string $stayTotal, array $nightlyTotals, string $deposit, string $paid, ?int $daysBeforeArrival): CancellationQuote
    {
        $policy = $policyId !== null ? CancellationPolicy::query()->with('rules')->find($policyId) : null;
        $terms = $policy instanceof CancellationPolicy ? $policy->terms() : new CancellationTerms([]);

        return $this->cancellations->quote($terms, $stayTotal, $nightlyTotals, $deposit, $paid, $daysBeforeArrival);
    }

    public function usePromotion(int $promotionId): void
    {
        Promotion::query()->whereKey($promotionId)->increment('times_used');
    }

    private function summary(RatePlan $plan): RatePlanSummary
    {
        return new RatePlanSummary($plan->id, $plan->property_id, $plan->code, $plan->name, $plan->meal_plan, $plan->meal_adult_amount,
            $plan->meal_child_amount, $plan->is_refundable, $plan->prices_include_tax, $plan->tax_category_id,
            $plan->valid_from?->toDateString(), $plan->valid_to?->toDateString(), $plan->channels ?? [], $plan->is_active);
    }
}
