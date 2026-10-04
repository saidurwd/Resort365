<?php

namespace Modules\Reservation\Listeners;

use Modules\Guest\Contracts\GuestLookup;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Reservation\Enums\GuestEmail;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationCancelled;
use Modules\Reservation\Events\ReservationConfirmed;
use Modules\Reservation\Events\ReservationCreated;
use Modules\Reservation\Jobs\SendGuestEmail;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Notifications\HoldExpiredNotice;

/**
 * The guest emails of a booking's life (ARCHITECTURE §4.5): received (with the deposit and how to
 * pay), confirmed (with the voucher), cancelled, or released because the deposit was not paid —
 * then the staff member who made the booking also gets an in-app notice. Emails are queued.
 */
class SendBookingNotifications
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly GuestLookup $guests,
    ) {}

    public function created(ReservationCreated $event): void
    {
        $reservation = Reservation::query()->find($event->reservationId);

        if ($reservation instanceof Reservation) {
            // A booking with no deposit due is confirmed at once: the confirmation (with the voucher) says it all.
            SendGuestEmail::dispatch($reservation->status === ReservationStatus::Confirmed ? GuestEmail::BookingConfirmed : GuestEmail::BookingCreated, $reservation->id);
        }
    }

    public function confirmed(ReservationConfirmed $event): void
    {
        SendGuestEmail::dispatch(GuestEmail::BookingConfirmed, $event->reservationId);
    }

    public function cancelled(ReservationCancelled $event): void
    {
        $money = fn (string $amount): string => number_format((float) $amount, 2);

        if (! $event->expired) {
            SendGuestEmail::dispatch(GuestEmail::BookingCancelled, $event->reservationId, ['fee' => $money($event->fee), 'refund' => $money($event->refundDue)]);

            return;
        }

        SendGuestEmail::dispatch(GuestEmail::HoldExpired, $event->reservationId);

        $reservation = Reservation::query()->find($event->reservationId);

        if ($reservation instanceof Reservation && $reservation->created_by !== null) {
            $this->users->notify([$reservation->created_by], new HoldExpiredNotice($reservation->code,
                $this->guests->find($reservation->primary_guest_id)->name ?? '', route('reservation.bookings.show', $reservation)));
        }
    }
}
