<?php

namespace Modules\Restaurant\Services;

use Carbon\CarbonImmutable;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\TableReservation;

/**
 * Table reservations as the POS floor and the back office show them (ARCHITECTURE §5.10.12): the day's
 * list in the property's time, and which tables to show as reserved (booked for the next hour, not yet seated).
 */
class TableReservations
{
    public const int RESERVED_MINUTES_AHEAD = 60;

    public function __construct(
        private readonly PropertyDirectory $properties,
    ) {}

    /**
     * An outlet's reservations for a local date (today when null), by time.
     *
     * @return list<TableReservation>
     */
    public function forDay(Outlet $outlet, ?string $date = null): array
    {
        $timezone = $this->properties->find($outlet->property_id)->timezone ?? 'UTC';
        $day = CarbonImmutable::parse($date ?? now($timezone)->toDateString(), $timezone);

        return TableReservation::query()->where('outlet_id', $outlet->id)->with('table')
            ->whereBetween('reserved_for', [$day->startOfDay()->utc(), $day->endOfDay()->utc()])->orderBy('reserved_for')->get()->all();
    }

    /**
     * Booked reservations still to come today (or just started), for the POS host.
     *
     * @return list<TableReservation>
     */
    public function coming(Outlet $outlet): array
    {
        $timezone = $this->properties->find($outlet->property_id)->timezone ?? 'UTC';

        return TableReservation::query()->where('outlet_id', $outlet->id)->where('status', TableReservationStatus::Booked->value)->with('table')
            ->whereBetween('reserved_for', [now()->subMinutes(45), CarbonImmutable::now($timezone)->endOfDay()->utc()])->orderBy('reserved_for')->get()->all();
    }

    /**
     * @return list<int> tables booked for the next hour (or an hour just gone) and not seated yet
     */
    public function reservedTableIds(int $outletId): array
    {
        return TableReservation::query()->where('outlet_id', $outletId)->where('status', TableReservationStatus::Booked->value)->whereNotNull('dining_table_id')
            ->whereBetween('reserved_for', [now()->subMinutes(45), now()->addMinutes(self::RESERVED_MINUTES_AHEAD)])->pluck('dining_table_id')->map(fn ($id): int => (int) $id)->all();
    }

    public function timezone(int $propertyId): string
    {
        return $this->properties->find($propertyId)->timezone ?? 'UTC';
    }
}
