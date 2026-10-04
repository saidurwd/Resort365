<?php

namespace Modules\Reservation\Services;

use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\NightOccupancy;
use Modules\Reservation\DTOs\ReservationSummary;
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
        ))->values()->all();
    }
}
