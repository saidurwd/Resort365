<?php

namespace Modules\Reservation\Support;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\DTOs\Occupancy;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Services\AvailabilityService;
use Modules\Reservation\Services\PartySplitter;

/**
 * The booking wizard's progress, kept in the session per property. Steps: dates (1), choose (2),
 * guest (3), pricing (4), confirm (5). Item keys are "cottage:{id}" and "room:{id}".
 */
class BookingWizard
{
    private const string KEY = 'reservation.booking_wizard';

    public function __construct(
        private readonly Session $session,
        private readonly InventoryCatalog $catalog,
        private readonly PartySplitter $splitter,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function state(int $propertyId): array
    {
        $state = (array) $this->session->get(self::KEY, []);

        return ($state['property_id'] ?? null) === $propertyId ? $state : ['property_id' => $propertyId];
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(int $propertyId, array $values): void
    {
        $this->session->put(self::KEY, [...$this->state($propertyId), ...$values, 'property_id' => $propertyId]);
    }

    /**
     * @param  list<string>  $keys
     */
    public function forget(int $propertyId, array $keys): void
    {
        $this->session->put(self::KEY, array_diff_key($this->state($propertyId), array_flip($keys)));
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * The first step that still needs input (1–5).
     */
    public function nextStep(int $propertyId): int
    {
        $state = $this->state($propertyId);

        return match (true) {
            ! isset($state['check_in']) => 1,
            empty($state['cottages']) && empty($state['rooms']) => 2,
            ! isset($state['guest_id']) && ! isset($state['new_guest']) => 3,
            ! isset($state['priced']) => 4,
            default => 5,
        };
    }

    /**
     * The chosen items with their guests (the split of the party unless set on step 4).
     *
     * @return array<string, array{type: ItemType, id: int, adults: int, children: int}>
     */
    public function items(int $propertyId): array
    {
        $state = $this->state($propertyId);
        $rooms = collect($this->catalog->rooms($propertyId))->keyBy('id');
        $cottages = collect($this->catalog->cottages($propertyId))->keyBy('id');
        $capacities = [];

        foreach ((array) ($state['cottages'] ?? []) as $id) {
            $cottage = $cottages->get((int) $id);

            if ($cottage instanceof CottageSummary) {
                $capacities['cottage:'.$cottage->id] = ['adults' => $cottage->maxOccupancy, 'children' => $cottage->maxOccupancy, 'total' => $cottage->maxOccupancy];
            }
        }

        foreach ((array) ($state['rooms'] ?? []) as $id) {
            $room = $rooms->get((int) $id);

            if ($room instanceof RoomSummary) {
                $capacities['room:'.$room->id] = ['adults' => $room->maxAdults, 'children' => $room->maxChildren, 'total' => $room->maxOccupancy];
            }
        }

        $split = $this->splitter->split($capacities, (int) ($state['adults'] ?? 1), (int) ($state['children'] ?? 0));
        $chosen = (array) ($state['occupancy'] ?? []);
        $items = [];

        foreach ($split as $key => $guests) {
            [$type, $id] = explode(':', $key);
            $items[$key] = [
                'type' => $type === 'cottage' ? ItemType::Cottage : ItemType::Room,
                'id' => (int) $id,
                'adults' => (int) ($chosen[$key]['adults'] ?? $guests['adults']),
                'children' => (int) ($chosen[$key]['children'] ?? $guests['children']),
            ];
        }

        return $items;
    }

    /**
     * What is free for the wizard's dates, party and rate plan (null before step 1).
     */
    public function availability(int $propertyId): ?AvailabilityResult
    {
        $state = $this->state($propertyId);

        if (! isset($state['check_in'], $state['check_out'], $state['rate_plan'])) {
            return null;
        }

        return app(AvailabilityService::class)->search(new AvailabilitySearch(
            $propertyId, (int) $state['rate_plan'], CarbonImmutable::parse((string) $state['check_in']), CarbonImmutable::parse((string) $state['check_out']),
            new Occupancy((int) ($state['adults'] ?? 1), (int) ($state['children'] ?? 0)),
        ));
    }

    /**
     * The booking as CreateReservation takes it (guestId: the chosen or just registered guest).
     */
    public function reservation(int $propertyId, int $guestId = 0, ?int $userId = null, bool $allowDepositOverride = false): NewReservation
    {
        $state = $this->state($propertyId);
        $plan = (int) ($state['rate_plan'] ?? 0);
        $text = fn (string $key): ?string => isset($state[$key]) && $state[$key] !== '' ? (string) $state[$key] : null;

        return new NewReservation(
            $propertyId,
            CarbonImmutable::parse((string) $state['check_in']),
            CarbonImmutable::parse((string) $state['check_out']),
            array_values(array_map(fn (array $item): BookingItem => new BookingItem($item['type'], $item['id'], $plan, max(1, $item['adults']), $item['children']), $this->items($propertyId))),
            $guestId,
            ReservationSource::tryFrom((string) ($state['source'] ?? '')) ?? ReservationSource::FrontDesk,
            isset($state['company_id']) ? (int) $state['company_id'] : null,
            isset($state['travel_agent_id']) ? (int) $state['travel_agent_id'] : null,
            $text('deposit_percent'),
            $text('promo_code'),
            $text('special_requests'),
            $text('internal_notes'),
            $userId,
            $allowDepositOverride,
        );
    }
}
