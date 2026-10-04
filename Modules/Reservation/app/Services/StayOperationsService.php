<?php

namespace Modules\Reservation\Services;

use Carbon\CarbonImmutable;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Rates\Contracts\RateLookup;
use Modules\Reservation\Actions\ChangeItemRoom;
use Modules\Reservation\Actions\CheckInReservation;
use Modules\Reservation\Actions\CheckOutReservation;
use Modules\Reservation\Actions\ExtendStay;
use Modules\Reservation\Actions\MoveRoom;
use Modules\Reservation\Actions\ShortenStay;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\DTOs\ReservationSummary;
use Modules\Reservation\DTOs\RoomNightCharge;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Exceptions\StayNotPossible;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Models\ReservationItemNight;

class StayOperationsService implements StayOperations
{
    public function __construct(
        private readonly CheckInReservation $checkIn,
        private readonly ChangeItemRoom $changeRoom,
        private readonly ReservationLookup $reservations,
        private readonly ItemLabels $labels,
        private readonly InventoryCatalog $catalog,
        private readonly CheckOutReservation $checkOut,
        private readonly RateLookup $rates,
        private readonly MoveRoom $move,
        private readonly ExtendStay $extend,
        private readonly ShortenStay $shorten,
    ) {}

    public function checkIn(int $reservationId, ?int $userId = null, ?int $itemId = null): array
    {
        return $this->checkIn->handle($reservationId, $userId, $itemId);
    }

    public function moveRoom(int $reservationItemId, int $roomId, bool $reprice = false, ?int $userId = null): void
    {
        $this->move->handle(ReservationItem::query()->findOrFail($reservationItemId), $roomId, $reprice, $userId);
    }

    public function extendStay(int $reservationId, string $checkOut, ?int $userId = null): ReservationSummary
    {
        $this->extend->handle(Reservation::query()->findOrFail($reservationId), CarbonImmutable::parse($checkOut), $userId);

        return $this->reservations->find($reservationId) ?? throw new StayNotPossible(__('Unknown reservation.'));
    }

    public function shortenStay(int $reservationId, string $checkOut, ?int $userId = null): ReservationSummary
    {
        $this->shorten->handle(Reservation::query()->findOrFail($reservationId), CarbonImmutable::parse($checkOut), $userId);

        return $this->reservations->find($reservationId) ?? throw new StayNotPossible(__('Unknown reservation.'));
    }

    public function changeRoom(int $reservationItemId, int $roomId, ?int $userId = null): void
    {
        $this->changeRoom->handle(ReservationItem::query()->findOrFail($reservationItemId), $roomId, $userId);
    }

    public function roomItems(int $reservationId): array
    {
        $items = [];

        foreach (ReservationItem::query()->where('reservation_id', $reservationId)->orderBy('id')->get() as $item) {
            $items[$item->id] = ['label' => $this->labels->of($item), 'room_id' => $item->item_type === ItemType::Room ? $item->room_id : null, 'room_type_id' => $item->room_type_id, 'status' => $item->status];
        }

        return $items;
    }

    public function roomIds(int $reservationId, ?array $itemIds = null): array
    {
        $ids = [];
        $cottages = null;

        foreach (ReservationItem::query()->where('reservation_id', $reservationId)->when($itemIds !== null, fn ($query) => $query->whereIn('id', $itemIds))->get() as $item) {
            if ($item->item_type === ItemType::Room && $item->room_id !== null) {
                $ids[] = $item->room_id;

                continue;
            }

            $cottages ??= collect($this->catalog->cottages($item->property_id))->keyBy('id');
            array_push($ids, ...($cottages->get($item->cottage_id)->roomIds ?? []));
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    public function unpostedNights(int $reservationId): array
    {
        $items = ReservationItem::query()->where('reservation_id', $reservationId)->get()->keyBy('id');
        $taxCategories = [];

        return ReservationItemNight::query()->whereIn('reservation_item_id', $items->keys())->whereNull('posted_to_folio_at')
            ->orderBy('stay_date')->orderBy('reservation_item_id')->get()
            ->map(function (ReservationItemNight $night) use ($items, &$taxCategories): RoomNightCharge {
                $item = $items->get($night->reservation_item_id);
                $planId = (int) $item?->rate_plan_id;
                $taxCategories[$planId] ??= $this->rates->ratePlan($planId)?->taxCategoryId;

                return new RoomNightCharge($night->id, $night->stay_date->toDateString(), $item instanceof ReservationItem ? $this->labels->of($item) : '',
                    $night->net_amount, $night->tax_amount, $night->total_amount, $taxCategories[$planId], $night->meal_amount);
            })->values()->all();
    }

    public function markNightsPosted(array $nightIds): void
    {
        ReservationItemNight::query()->whereIn('id', $nightIds)->whereNull('posted_to_folio_at')->update(['posted_to_folio_at' => now()]);
    }

    public function checkOut(int $reservationId, ?int $userId = null): ReservationSummary
    {
        $this->checkOut->handle($reservationId, $userId);

        return $this->reservations->find($reservationId) ?? throw new StayNotPossible(__('Unknown reservation.'));
    }
}
