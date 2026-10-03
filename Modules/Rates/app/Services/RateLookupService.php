<?php

namespace Modules\Rates\Services;

use Carbon\CarbonImmutable;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\PromotionDiscount;
use Modules\Rates\DTOs\PromotionTerms;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Rates\DTOs\StayRequest;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RatePlan;

class RateLookupService implements RateLookup
{
    public function __construct(
        private readonly RateCalendar $calendar,
        private readonly PromotionMatcher $promotions,
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

    private function summary(RatePlan $plan): RatePlanSummary
    {
        return new RatePlanSummary($plan->id, $plan->property_id, $plan->code, $plan->name, $plan->meal_plan, $plan->meal_adult_amount,
            $plan->meal_child_amount, $plan->is_refundable, $plan->prices_include_tax, $plan->tax_category_id,
            $plan->valid_from?->toDateString(), $plan->valid_to?->toDateString(), $plan->channels ?? [], $plan->is_active);
    }
}
