<?php

namespace Modules\Reservation\Services;

use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Property\DTOs\UnitTypeSummary;
use Modules\Property\Enums\UnitKind;
use Modules\Rates\Contracts\RateLookup;
use Modules\Rates\DTOs\RatePlanSummary;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\CottageOption;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\DTOs\PriceQuote;
use Modules\Reservation\DTOs\RestrictionViolation;
use Modules\Reservation\DTOs\RoomTypeOption;
use Modules\Reservation\Enums\StayRestriction;
use Modules\Reservation\Models\InventoryLock;

/**
 * Availability search (ARCHITECTURE §6.2): free whole cottages and rooms for a stay, with rate
 * restrictions, whether the party fits, and a price quote for each.
 */
class AvailabilityService
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly RateLookup $rates,
        private readonly AvailabilityCalculator $availability,
        private readonly RestrictionChecker $restrictions,
        private readonly PricingService $pricing,
    ) {}

    /**
     * Rooms with a lock on any night of [checkIn, checkOut).
     *
     * @return list<int>
     */
    public function lockedRoomIds(AvailabilitySearch $search): array
    {
        return InventoryLock::query()->where('property_id', $search->propertyId)
            ->where('stay_date', '>=', $search->checkIn->toDateString())->where('stay_date', '<', $search->checkOut->toDateString())
            ->distinct()->pluck('room_id')->map(intval(...))->values()->all();
    }

    public function search(AvailabilitySearch $search): ?AvailabilityResult
    {
        $plan = $this->rates->ratePlan($search->ratePlanId);

        if (! $plan instanceof RatePlanSummary) {
            return null;
        }

        $rooms = $this->catalog->rooms($search->propertyId);
        $cottages = $this->catalog->cottages($search->propertyId);
        $types = [];

        foreach ($this->catalog->unitTypes($search->propertyId) as $type) {
            $types[$type->key()] = $type;
        }

        $free = $this->availability->calculate($rooms, $cottages, $this->lockedRoomIds($search));
        $keys = array_keys($types);
        $lastNight = $search->checkOut->subDay();
        $nightly = $this->rates->nightlyRates($plan->id, $keys, $search->checkIn, $lastNight);
        $restrictions = $this->rates->restrictions($plan->id, $keys, $search->checkIn, $search->checkOut);
        $roomsById = [];

        foreach ($rooms as $room) {
            $roomsById[$room->id] = $room;
        }

        $cottageOptions = array_map(function (CottageSummary $cottage) use ($search, $plan, $types, $roomsById, $nightly, $restrictions): CottageOption {
            $cottageRooms = array_values(array_filter(array_map(fn (int $id): ?RoomSummary => $roomsById[$id] ?? null, $cottage->roomIds)));
            $fits = $search->occupancy->total() <= $cottage->maxOccupancy;
            $occupancy = $fits ? $search->occupancy : new Occupancy(min($search->occupancy->adults, $cottage->maxOccupancy), 0);
            $quote = $this->pricing->priceCottage($plan, $cottage, $cottageRooms, $nightly, $occupancy, $search->checkIn, $search->promoCode, $fits);

            return new CottageOption($cottage, $types[$cottage->unitKey()]->name ?? '', array_map(fn (RoomSummary $room): string => $room->number, $cottageRooms),
                $fits, $quote, $this->violations($restrictions[$cottage->unitKey()] ?? [], $search, ! $quote instanceof PriceQuote));
        }, $free->wholeCottages);

        $roomTypeOptions = [];

        foreach (collect($free->rooms)->groupBy(fn (RoomSummary $room): string => $room->unitKey()) as $key => $group) {
            $type = $types[$key] ?? null;

            if (! $type instanceof UnitTypeSummary || $type->kind !== UnitKind::RoomType) {
                continue;
            }

            /** @var list<RoomSummary> $typeRooms */
            $typeRooms = $group->values()->all();
            $fits = collect($typeRooms)->contains(fn (RoomSummary $room): bool => $search->occupancy->adults <= $room->maxAdults
                && $search->occupancy->children <= $room->maxChildren && $search->occupancy->total() <= $room->maxOccupancy);
            $occupancy = $fits ? $search->occupancy : new Occupancy($type->baseOccupancy, 0);
            $quote = $this->pricing->priceRoomType($plan, $type, $nightly[$key] ?? [], $occupancy, $search->checkIn, $search->promoCode, $fits);

            $roomTypeOptions[] = new RoomTypeOption($type, $typeRooms, $fits, $quote, $this->violations($restrictions[$key] ?? [], $search, ! $quote instanceof PriceQuote));
        }

        return new AvailabilityResult($search, $plan, $cottageOptions, $roomTypeOptions, $this->planViolations($plan, $search));
    }

    /**
     * @param  array<string, RestrictionSet>  $byDate
     * @return list<RestrictionViolation>
     */
    private function violations(array $byDate, AvailabilitySearch $search, bool $noRate): array
    {
        $violations = $this->restrictions->check($byDate, $search->checkIn, $search->checkOut);

        return $noRate ? [...$violations, new RestrictionViolation(StayRestriction::NoRate)] : $violations;
    }

    /**
     * @return list<RestrictionViolation>
     */
    private function planViolations(RatePlanSummary $plan, AvailabilitySearch $search): array
    {
        $valid = $plan->isActive && $plan->sellsThrough('front_desk')
            && $plan->isValidOn($search->checkIn->toDateString()) && $plan->isValidOn($search->checkOut->subDay()->toDateString());

        return $valid ? [] : [new RestrictionViolation(StayRestriction::PlanNotValid)];
    }
}
