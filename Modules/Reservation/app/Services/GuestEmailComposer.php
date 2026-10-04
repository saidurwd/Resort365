<?php

namespace Modules\Reservation\Services;

use Illuminate\Support\Facades\Notification;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Contracts\Settings;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\GuestSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;
use Modules\Reservation\Enums\GuestEmail;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Models\Quote;
use Modules\Reservation\Models\QuoteItem;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use Modules\Reservation\Notifications\GuestMessage;

/**
 * Writes and sends a guest email (ARCHITECTURE §4.5, §5.6): the template's wording filled with the
 * booking's or quote's details, the voucher or quotation attached, sent to the primary guest. The
 * booking's history records it, or records that the guest has no email address.
 */
class GuestEmailComposer
{
    public function __construct(
        private readonly NotificationTemplates $templates,
        private readonly GuestLookup $guests,
        private readonly PropertyDirectory $properties,
        private readonly Settings $settings,
        private readonly BookingDocuments $documents,
        private readonly ItemLabels $labels,
        private readonly ReservationLogger $logger,
    ) {}

    /**
     * @param  array<string, string>  $extra  more placeholders (fee, refund)
     * @return bool whether an email was sent
     */
    public function sendForReservation(GuestEmail $email, Reservation $reservation, array $extra = []): bool
    {
        $guest = $this->guests->find($reservation->primary_guest_id);
        $property = $this->properties->find($reservation->property_id);

        if (! $guest instanceof GuestSummary || $guest->email === null || $guest->email === '') {
            $this->logger->log($reservation, ReservationLogAction::EmailSent, __('":email" not sent: the guest has no email address.', ['email' => $email->label()]));

            return false;
        }

        $money = fn (?string $amount): string => number_format((float) $amount, 2);
        $data = [
            ...$this->common($guest, $property),
            'code' => $reservation->code,
            'check_in' => $reservation->check_in->format('D d M Y'),
            'check_out' => $reservation->check_out->format('D d M Y'),
            'nights' => (string) $reservation->nights(),
            'rooms' => $reservation->items()->get()->map(fn (ReservationItem $item): string => $this->labels->of($item))->implode(', '),
            'currency' => $reservation->currency_code,
            'total' => $money($reservation->grand_total),
            'deposit' => $money($reservation->deposit_required),
            'deposit_due' => $reservation->deposit_due_at?->setTimezone($property->timezone ?? 'UTC')->format('d M Y H:i') ?? '',
            'paid' => $money($reservation->amount_paid),
            'balance' => $money($reservation->balance_due),
            'payment_instructions' => (string) $this->settings->get('reservation.payment_instructions', $reservation->property_id),
            ...$extra,
        ];

        $attachments = $email === GuestEmail::BookingConfirmed ? [$reservation->code.'.pdf' => $this->documents->voucher($reservation)] : [];
        $this->send($email, $guest, $property, $data, $attachments);

        $this->logger->log($reservation, ReservationLogAction::EmailSent, __('":email" sent to :address.', ['email' => $email->label(), 'address' => $guest->email]));

        return true;
    }

    /**
     * @return bool whether an email was sent (false when the guest has no email address)
     */
    public function sendQuote(Quote $quote): bool
    {
        $guest = $this->guests->find($quote->guest_id);

        if (! $guest instanceof GuestSummary || $guest->email === null || $guest->email === '') {
            return false;
        }

        $property = $this->properties->find($quote->property_id);
        $money = fn (?string $amount): string => number_format((float) $amount, 2);

        $this->send(GuestEmail::Quote, $guest, $property, [
            ...$this->common($guest, $property),
            'code' => $quote->code,
            'check_in' => $quote->check_in->format('D d M Y'),
            'check_out' => $quote->check_out->format('D d M Y'),
            'nights' => (string) $quote->nights(),
            'rooms' => $quote->items()->get()->map(fn (QuoteItem $item): string => $item->label)->implode(', '),
            'currency' => $quote->currency_code,
            'total' => $money($quote->grand_total),
            'deposit' => $money($quote->deposit_amount),
            'valid_until' => $quote->valid_until->format('d M Y'),
        ], [$quote->code.'.pdf' => $this->documents->quote($quote)]);

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function common(GuestSummary $guest, ?PropertySummary $property): array
    {
        return [
            'guest' => $guest->name,
            'property' => $property->name ?? '',
            'property_phone' => $property->phone ?? '',
            'property_email' => $property->email ?? '',
            'check_in_time' => $property->checkInTime ?? '',
            'check_out_time' => $property->checkOutTime ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $data
     * @param  array<string, string>  $attachments
     */
    private function send(GuestEmail $email, GuestSummary $guest, ?PropertySummary $property, array $data, array $attachments): void
    {
        $rendered = $this->templates->render($email->value, 'mail', $data);

        Notification::route('mail', [(string) $guest->email => $guest->name])
            ->notifyNow(new GuestMessage((string) $rendered->subject, $rendered->body, $property->name ?? (string) config('app.name'), $attachments));
    }
}
