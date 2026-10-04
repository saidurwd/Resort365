<?php

namespace Modules\Reservation\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;

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
            $reservation->adults, $reservation->children,
        ))->values()->all();
    }
}
