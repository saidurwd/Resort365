<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Contracts\ReservationAnalytics;
use Modules\Reservation\DTOs\BookingFact;
use Modules\Reservation\DTOs\ForecastDay;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;

class ReservationAnalyticsService implements ReservationAnalytics
{
    public function __construct(
        private readonly InventoryCatalog $catalog,
        private readonly PropertyDirectory $properties,
    ) {}

    public function bookings(array $propertyIds, string $by, string $from, string $to): array
    {
        $zones = [];

        foreach ($propertyIds as $id) {
            $zones[$id] = $this->properties->find($id)->timezone ?? 'UTC';
        }

        $query = Reservation::query()->whereIn('property_id', $propertyIds)->withCount('items');

        if ($by === 'arrival') {
            $query->whereBetween('check_in', [$from, $to]);
        } else {
            // The booking date is the property's day, so the window is that day's start and end in its own time zone.
            $query->where(function ($inner) use ($zones, $from, $to): void {
                foreach ($zones as $id => $zone) {
                    $inner->orWhere(fn ($q) => $q->where('property_id', $id)->whereBetween('created_at', [
                        CarbonImmutable::parse($from, $zone)->startOfDay()->utc(), CarbonImmutable::parse($to, $zone)->endOfDay()->utc(),
                    ]));
                }
            });
        }

        return $query->orderBy('id')->get(['id', 'code', 'property_id', 'source', 'status', 'created_at', 'check_in', 'check_out', 'grand_total', 'cancellation_fee', 'cancelled_at', 'primary_guest_id', 'company_id', 'travel_agent_id', 'adults', 'children'])
            ->map(fn (Reservation $r): BookingFact => new BookingFact(
                $r->id, $r->code, $r->property_id, $r->source->value, $r->status->value, $r->created_at->setTimezone($zones[$r->property_id])->toDateString(), $r->check_in->toDateString(), $r->check_out->toDateString(), $r->nights(),
                max(1, (int) $r->getAttribute('items_count')), (string) $r->grand_total, (string) ($r->cancellation_fee ?? '0.00'), $r->cancelled_at?->setTimezone($zones[$r->property_id])->toDateString(),
                $r->primary_guest_id, $r->company_id, $r->travel_agent_id, (int) $r->adults, (int) $r->children,
            ))->all();
    }

    public function forecast(array $propertyIds, string $from, string $to): array
    {
        $total = 0;

        foreach ($propertyIds as $id) {
            $total += count(array_filter($this->catalog->rooms($id), fn (RoomSummary $room): bool => $room->isActive));
        }

        $locks = [];

        foreach (InventoryLock::query()->whereIn('property_id', $propertyIds)->whereBetween('stay_date', [$from, $to])->toBase()
            ->selectRaw('stay_date, lock_type, COUNT(*) as n')->groupBy('stay_date', 'lock_type')->get() as $row) {
            $locks[substr((string) $row->stay_date, 0, 10)][(string) $row->lock_type] = (int) $row->n;
        }

        $arrivals = Reservation::query()->whereIn('property_id', $propertyIds)->whereBetween('check_in', [$from, $to])
            ->whereIn('status', [ReservationStatus::Tentative->value, ReservationStatus::Confirmed->value])->toBase()->selectRaw('check_in, COUNT(*) as n')->groupBy('check_in')->get()
            ->mapWithKeys(fn ($row): array => [substr((string) $row->check_in, 0, 10) => (int) $row->n])->all();

        $days = [];

        for ($date = CarbonImmutable::parse($from); $date->lte(CarbonImmutable::parse($to)); $date = $date->addDay()) {
            $key = $date->toDateString();
            $day = $locks[$key] ?? [];
            $days[] = new ForecastDay($key, $total, ($day[LockType::OutOfOrder->value] ?? 0) + ($day[LockType::OwnerBlock->value] ?? 0), ($day[LockType::Reservation->value] ?? 0) + ($day[LockType::Hold->value] ?? 0), $arrivals[$key] ?? 0);
        }

        return $days;
    }

    public function stayCounts(array $guestIds): array
    {
        if ($guestIds === []) {
            return [];
        }

        return Reservation::query()->whereIn('primary_guest_id', $guestIds)->whereIn('status', [ReservationStatus::CheckedIn->value, ReservationStatus::CheckedOut->value])
            ->toBase()->selectRaw('primary_guest_id, COUNT(*) as n')->groupBy('primary_guest_id')->get()
            ->mapWithKeys(fn ($row): array => [(int) $row->primary_guest_id => (int) $row->n])->all();
    }
}
