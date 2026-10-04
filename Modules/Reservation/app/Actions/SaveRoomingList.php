<?php

namespace Modules\Reservation\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Contracts\GuestRegistry;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Exceptions\BookingNotPossible;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationGuest;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Services\ItemLabels;
use Modules\Reservation\Services\ReservationLogger;

/**
 * The rooming list of a group (ARCHITECTURE §5.6): the guest of each room or cottage, chosen from
 * the guest profiles or created by name. A room's previous guest is replaced (the primary guest of
 * the booking stays on it); blacklisted guests are refused.
 */
class SaveRoomingList extends Action
{
    public function __construct(
        private readonly GuestLookup $guests,
        private readonly GuestRegistry $registry,
        private readonly ItemLabels $labels,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @param  array<int, array{guest_id?: int|null, new_name?: string|null}>  $rows  item id => guest
     * @return int how many rooms got a guest
     *
     * @throws BookingNotPossible
     */
    public function handle(Reservation $reservation, array $rows, ?int $userId = null): int
    {
        if (! $reservation->isChangeable() && $reservation->status !== ReservationStatus::CheckedIn) {
            throw new BookingNotPossible(__('The rooming list of a :status booking cannot change.', ['status' => strtolower($reservation->status->label())]));
        }

        return $this->transaction(function () use ($reservation, $rows, $userId): int {
            $items = ReservationItem::query()->where('reservation_id', $reservation->id)->get()->keyBy('id');
            $count = 0;
            $named = [];

            foreach ($rows as $itemId => $row) {
                $item = $items->get($itemId);
                $guest = $this->guestFor($row);

                if (! $item instanceof ReservationItem || ! $guest instanceof GuestSummary) {
                    continue;
                }

                if ($guest->isBlacklisted) {
                    throw new BookingNotPossible(__(':name is blacklisted and cannot be added.', ['name' => $guest->name]));
                }

                ReservationGuest::query()->where('reservation_id', $reservation->id)->where('reservation_item_id', $item->id)->where('is_primary', false)->delete();
                $existing = ReservationGuest::query()->where('reservation_id', $reservation->id)->where('guest_id', $guest->id)->first();

                if ($existing instanceof ReservationGuest) {
                    $existing->forceFill(['reservation_item_id' => $item->id])->save();
                } else {
                    $reservation->guests()->create(['property_id' => $reservation->property_id, 'reservation_item_id' => $item->id, 'guest_id' => $guest->id, 'is_primary' => false]);
                }

                $named[] = $this->labels->of($item).': '.$guest->name;
                $count++;
            }

            if ($count > 0) {
                $this->logger->log($reservation, ReservationLogAction::GuestAdded, __('Rooming list: :rooms.', ['rooms' => implode('; ', $named)]), userId: $userId);
            }

            return $count;
        });
    }

    /**
     * @param  array{guest_id?: int|null, new_name?: string|null}  $row
     */
    private function guestFor(array $row): ?GuestSummary
    {
        if (! empty($row['guest_id'])) {
            return $this->guests->find((int) $row['guest_id']);
        }

        $name = trim((string) ($row['new_name'] ?? ''));

        if ($name === '') {
            return null;
        }

        [$first, $last] = array_pad(preg_split('/\s+/', $name, 2) ?: [$name], 2, null);

        return $this->registry->register(new GuestDetails((string) $first, $last));
    }
}
