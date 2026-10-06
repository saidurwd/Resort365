<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\ChargeableStay;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Enums\TableReservationStatus;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\TableReservation;
use Modules\Restaurant\Services\TableAvailability;

/**
 * Books or changes a table reservation (ARCHITECTURE §5.10.12): a date and time in the property's time, a
 * party size, an optional table (which must seat the party and be free for the slot), and an in-house
 * guest (linked to their booking) or an outside customer's name and phone. Only booked reservations change.
 */
class SaveTableReservation extends Action
{
    public function __construct(
        private readonly TableAvailability $availability,
        private readonly PropertyDirectory $properties,
        private readonly FolioPostingContract $folios,
    ) {}

    /**
     * @param  array{date: string, time: string, party_size: int, dining_table_id?: int|null, reservation_id?: int|null, customer_name?: string|null,
     *     phone?: string|null, occasion?: string|null, notes?: string|null, duration_minutes?: int|null}  $data
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(Outlet $outlet, ?TableReservation $reservation, array $data, ?int $userId = null): TableReservation
    {
        if ($reservation instanceof TableReservation && $reservation->status !== TableReservationStatus::Booked) {
            throw new RestaurantSetupInvalid(__('Only a booked reservation can be changed.'));
        }

        $timezone = $this->properties->find($outlet->property_id)->timezone ?? 'UTC';
        $start = CarbonImmutable::parse($data['date'].' '.$data['time'], $timezone)->utc();
        $minutes = (int) ($data['duration_minutes'] ?? 90) ?: 90;
        $stay = isset($data['reservation_id']) ? collect($this->folios->chargeableStays($outlet->property_id))->firstWhere('reservationId', (int) $data['reservation_id']) : null;

        if (isset($data['reservation_id']) && ! $stay instanceof ChargeableStay) {
            throw new RestaurantSetupInvalid(__('Choose a guest who is in house, or leave it for an outside customer.'));
        }

        $name = $stay instanceof ChargeableStay ? $stay->guestName : trim((string) ($data['customer_name'] ?? ''));

        if ($name === '') {
            throw new RestaurantSetupInvalid(__('Enter the customer\'s name.'));
        }

        return $this->transaction(function () use ($outlet, $reservation, $data, $userId, $start, $minutes, $stay, $name): TableReservation {
            $tableId = $data['dining_table_id'] ?? null;

            if ($tableId !== null) {
                $table = DiningTable::query()->where('outlet_id', $outlet->id)->where('is_active', true)->lockForUpdate()->find($tableId)
                    ?? throw new RestaurantSetupInvalid(__('Choose a table of this outlet.'));

                if (! $this->availability->seats($table->seats, (int) $data['party_size'])) {
                    throw new RestaurantSetupInvalid(__('Table :number seats :seats, for a party of :party.', ['number' => $table->number, 'seats' => $table->seats, 'party' => $data['party_size']]));
                }

                $others = TableReservation::query()->where('dining_table_id', $table->id)->whereIn('status', [TableReservationStatus::Booked->value, TableReservationStatus::Seated->value])
                    ->when($reservation instanceof TableReservation, fn ($query) => $query->whereKeyNot($reservation->id))
                    ->whereBetween('reserved_for', [$start->subDay(), $start->addDay()])->get()
                    ->map(fn (TableReservation $other): array => ['start' => CarbonImmutable::instance($other->reserved_for), 'minutes' => $other->duration_minutes])->all();

                if ($this->availability->clashes($start, $minutes, $others)) {
                    throw new RestaurantSetupInvalid(__('Table :number is already booked around that time.', ['number' => $table->number]));
                }
            }

            $fields = [
                'property_id' => $outlet->property_id, 'outlet_id' => $outlet->id, 'dining_table_id' => $tableId, 'reservation_id' => $stay?->reservationId,
                'customer_name' => mb_substr($name, 0, 190), 'phone' => $data['phone'] ?? null, 'reserved_for' => $start, 'duration_minutes' => $minutes,
                'party_size' => (int) $data['party_size'], 'occasion' => $data['occasion'] ?? null, 'notes' => $data['notes'] ?? null,
            ];

            if ($reservation instanceof TableReservation) {
                $reservation->fill($fields)->save();

                return $reservation;
            }

            return TableReservation::query()->create([...$fields, 'status' => TableReservationStatus::Booked, 'created_by' => $userId]);
        });
    }
}
