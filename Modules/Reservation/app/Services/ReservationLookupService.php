<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\MealEntitlement;
use Modules\Reservation\DTOs\NightOccupancy;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\DTOs\RoomOccupancy;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Models\ReservationItemNight;

class ReservationLookupService implements ReservationLookup
{
    public function __construct(
        private readonly ItemLabels $labels,
        private readonly GuestLookup $guests,
        private readonly RateLookup $rates,
        private readonly MealEntitlements $meals,
    ) {}

    public function find(int $reservationId): ?ReservationSummary
    {
        $reservation = Reservation::query()->with('items')->find($reservationId);

        return $reservation instanceof Reservation ? $this->summaries(new Collection([$reservation]))[0] : null;
    }

    public function arrivals(int $propertyId, string $date): array
    {
        return $this->list(Reservation::query()->where('property_id', $propertyId)->where('check_in', $date)
            ->whereIn('status', [ReservationStatus::Tentative->value, ReservationStatus::Confirmed->value])->orderBy('code'));
    }

    public function departures(int $propertyId, string $date): array
    {
        return $this->list(Reservation::query()->where('property_id', $propertyId)->where('check_out', '<=', $date)
            ->where('status', ReservationStatus::CheckedIn->value)->orderBy('check_out')->orderBy('code'));
    }

    public function inHouse(int $propertyId): array
    {
        return $this->list(Reservation::query()->where('property_id', $propertyId)->where('status', ReservationStatus::CheckedIn->value)->orderBy('check_out')->orderBy('code'));
    }

    public function pendingDeposits(int $propertyId): array
    {
        return $this->list(Reservation::query()->where('property_id', $propertyId)->where('status', ReservationStatus::Tentative->value)
            ->whereColumn('amount_paid', '<', 'deposit_required')->orderBy('deposit_due_at'));
    }

    public function roomsBooked(int $propertyId, string $date): int
    {
        return InventoryLock::query()->where('property_id', $propertyId)->where('stay_date', $date)->where('lock_type', LockType::Reservation->value)->count();
    }

    public function occupancy(int $propertyId, string $date): NightOccupancy
    {
        $stayed = [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value];
        $items = ReservationItem::query()->where('property_id', $propertyId)->whereIn('status', $stayed)
            ->where('check_in', '<=', $date)->where('check_out', '>', $date)->get(['id', 'adults', 'children']);
        $locks = InventoryLock::query()->where('property_id', $propertyId)->where('stay_date', $date)->get(['lock_type', 'reservation_item_id']);
        $nights = ReservationItemNight::query()->whereIn('reservation_item_id', $items->pluck('id'))->where('stay_date', $date)
            ->get(['net_amount', 'meal_amount', 'tax_amount']);
        $sum = fn (string $field): BigDecimal => $nights->reduce(fn (BigDecimal $total, ReservationItemNight $night): BigDecimal => $total->plus($night->{$field}), BigDecimal::zero());
        $count = fn (array $statuses, string $column): int => Reservation::query()->where('property_id', $propertyId)->whereIn('status', $statuses)->where($column, $date)->count();

        return new NightOccupancy(
            date: $date,
            roomsOccupied: $locks->filter(fn (InventoryLock $lock): bool => $lock->lock_type === LockType::Reservation && $items->contains('id', $lock->reservation_item_id))->count(),
            roomsOutOfOrder: $locks->filter(fn (InventoryLock $lock): bool => $lock->lock_type === LockType::OutOfOrder)->count(),
            roomsBlocked: $locks->filter(fn (InventoryLock $lock): bool => in_array($lock->lock_type, [LockType::OwnerBlock, LockType::Hold], true))->count(),
            adults: (int) $items->sum('adults'),
            children: (int) $items->sum('children'),
            roomRevenue: (string) $sum('net_amount')->minus($sum('meal_amount'))->toScale(2),
            packageMealRevenue: (string) $sum('meal_amount')->toScale(2),
            roomTax: (string) $sum('tax_amount')->toScale(2),
            arrivals: $count($stayed, 'check_in'),
            departures: $count([ReservationStatus::CheckedOut->value], 'check_out'),
            noShows: $count([ReservationStatus::NoShow->value], 'check_in'),
        );
    }

    public function roomOccupancy(int $propertyId, string $date): array
    {
        $yesterday = CarbonImmutable::parse($date)->subDay()->toDateString();
        $locks = InventoryLock::query()->where('property_id', $propertyId)->whereIn('stay_date', [$date, $yesterday])
            ->where('lock_type', LockType::Reservation->value)->get(['room_id', 'stay_date', 'reservation_id', 'reservation_item_id']);
        $items = ReservationItem::query()->whereIn('id', $locks->pluck('reservation_item_id')->filter()->unique())->get(['id', 'status', 'check_in', 'check_out'])->keyBy('id');
        $reservations = Reservation::query()->whereIn('id', $locks->pluck('reservation_id')->filter()->unique())->get(['id', 'code', 'primary_guest_id', 'group_name'])->keyBy('id');
        $names = $this->guests->names($reservations->pluck('primary_guest_id')->unique()->values()->all());
        $rooms = [];

        foreach ($locks as $lock) {
            $item = $items->get($lock->reservation_item_id);

            if (! $item instanceof ReservationItem) {
                continue;
            }

            $tonight = $lock->stay_date->toDateString() === $date;
            $kind = match (true) {
                $tonight && $item->status === ReservationStatus::CheckedIn => 'occupied',
                ! $tonight && $item->status === ReservationStatus::CheckedIn && $item->check_out->toDateString() === $date => 'departing',
                $tonight && in_array($item->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed], true) && $item->check_in->toDateString() === $date => 'arriving',
                default => null,
            };

            if ($kind !== null) {
                $rooms[$lock->room_id][$kind] = $lock->reservation_id;
            }
        }

        $result = [];

        foreach ($rooms as $roomId => $kinds) {
            $reservationId = $kinds['occupied'] ?? $kinds['departing'] ?? $kinds['arriving'] ?? null;
            $reservation = $reservationId !== null ? $reservations->get($reservationId) : null;
            $result[(int) $roomId] = new RoomOccupancy((int) $roomId, isset($kinds['occupied']), isset($kinds['departing']), isset($kinds['arriving']),
                $reservationId, $reservation?->code, $reservation instanceof Reservation ? ($reservation->group_name ?? ($names[$reservation->primary_guest_id] ?? null)) : null);
        }

        return $result;
    }

    public function mealEntitlement(int $reservationId, string $date): MealEntitlement
    {
        return $this->entitlements(ReservationItem::query()->where('reservation_id', $reservationId)->whereIn('status', $this->stayed())->get(), $date)[$reservationId]
            ?? new MealEntitlement($reservationId, $date, [], []);
    }

    public function mealEntitlements(int $propertyId, string $date): array
    {
        return array_values($this->entitlements(ReservationItem::query()->where('property_id', $propertyId)->whereIn('status', $this->stayed())
            ->where('check_in', '<=', $date)->where('check_out', '>=', $date)->get(), $date));
    }

    /**
     * @return list<string>
     */
    private function stayed(): array
    {
        return [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value];
    }

    /**
     * @param  Collection<int, ReservationItem>  $items
     * @return array<int, MealEntitlement> by reservation id
     */
    private function entitlements(Collection $items, string $date): array
    {
        $periods = [];
        $plans = [];

        foreach ($items as $item) {
            $plan = $this->rates->ratePlan($item->rate_plan_id)?->mealPlan->value ?? 'EP';
            $plans[$item->reservation_id][] = $plan;

            foreach ($this->meals->periods($plan, $item->check_in->toDateString(), $item->check_out->toDateString(), $date) as $period) {
                $periods[$item->reservation_id][$period]['adults'] = ($periods[$item->reservation_id][$period]['adults'] ?? 0) + $item->adults;
                $periods[$item->reservation_id][$period]['children'] = ($periods[$item->reservation_id][$period]['children'] ?? 0) + $item->children;
            }
        }

        $result = [];

        foreach ($plans as $id => $reservationPlans) {
            $result[$id] = new MealEntitlement($id, $date, $periods[$id] ?? [], array_values(array_unique($reservationPlans)));
        }

        return $result;
    }

    /**
     * @param  Builder<Reservation>  $query
     * @return list<ReservationSummary>
     */
    private function list(Builder $query): array
    {
        return $this->summaries($query->with('items')->limit(500)->get());
    }

    /**
     * @param  Collection<int, Reservation>  $reservations
     * @return list<ReservationSummary>
     */
    private function summaries(Collection $reservations): array
    {
        $names = $this->guests->names($reservations->pluck('primary_guest_id')->unique()->values()->all());

        return $reservations->map(fn (Reservation $reservation): ReservationSummary => new ReservationSummary(
            $reservation->id, $reservation->property_id, $reservation->code, $reservation->status, $reservation->payment_status,
            $reservation->primary_guest_id, $reservation->check_in->toDateString(), $reservation->check_out->toDateString(),
            $reservation->currency_code, $reservation->grand_total, $reservation->deposit_required, $reservation->amount_paid,
            $reservation->balance_due, $reservation->cancellation_fee,
            $reservation->items->map(fn (ReservationItem $item): string => $this->labels->of($item))->values()->all(),
            $names[$reservation->primary_guest_id] ?? '',
            $reservation->deposit_due_at?->toIso8601String(),
            $reservation->adults, $reservation->children, $reservation->group_name, $reservation->items->count(),
            $reservation->items->filter(fn (ReservationItem $item): bool => $item->status === ReservationStatus::CheckedIn)->count(),
            $reservation->no_room_charges,
        ))->values()->all();
    }
}
