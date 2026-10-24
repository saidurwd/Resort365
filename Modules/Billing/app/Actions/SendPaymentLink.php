<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Modules\Billing\Contracts\OnlinePayments;
use Modules\Billing\Enums\OnlinePaymentPurpose;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Notifications\PaymentLinkMessage;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * Emails the guest a link to pay their deposit or balance online (Step 8.3): a signed link valid for a week to a page where they pick
 * a gateway. The amount is worked out again when they pay, so a link never asks for more than is still owed.
 */
class SendPaymentLink extends Action
{
    public const int DAYS = 7;

    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly GuestLookup $guests,
        private readonly PropertyDirectory $properties,
        private readonly OnlinePayments $online,
        private readonly NotificationTemplates $templates,
    ) {}

    /**
     * @return string the link
     *
     * @throws PaymentNotAllowed
     */
    public function handle(int $reservationId, OnlinePaymentPurpose $purpose): string
    {
        $reservation = $this->reservations->find($reservationId) ?? throw new PaymentNotAllowed(__('Unknown reservation.'));
        $guest = $this->guests->find($reservation->primaryGuestId);
        $amount = self::amountDue($reservation, $purpose);

        if (! $reservation->acceptsPayments() || ! $amount->isPositive()) {
            throw new PaymentNotAllowed(__('Nothing is due to pay online for this booking.'));
        }

        if ($this->online->options($reservation->propertyId) === []) {
            throw new PaymentNotAllowed(__('No payment gateway is switched on for this property (Setup → Online payments).'));
        }

        if ($guest?->email === null || $guest->email === '') {
            throw new PaymentNotAllowed(__('The guest has no email address.'));
        }

        $expires = now()->addDays(self::DAYS);
        $link = URL::temporarySignedRoute('billing.pay-link.show', $expires, ['reservation' => $reservation->id, 'purpose' => $purpose->value]);
        $property = $this->properties->find($reservation->propertyId);
        $rendered = $this->templates->render('billing.payment_link', 'mail', [
            'guest' => $guest->name, 'code' => $reservation->code, 'property' => $property->name ?? (string) config('app.name'), 'currency' => $reservation->currencyCode,
            'amount' => number_format((float) (string) $amount, 2), 'what' => strtolower($purpose->label()), 'link' => $link, 'expires' => $expires->format('d M Y'),
        ]);

        Notification::route('mail', [$guest->email => $guest->name])->notifyNow(new PaymentLinkMessage((string) $rendered->subject, $rendered->body, $link, $property->name ?? (string) config('app.name')));

        return $link;
    }

    /** What a payment of this kind would be now: the deposit still owed, or everything still owed. */
    public static function amountDue(ReservationSummary $reservation, OnlinePaymentPurpose $purpose): BigDecimal
    {
        $owed = BigDecimal::of($reservation->grandTotal)->minus($reservation->amountPaid);
        $deposit = BigDecimal::of($reservation->depositRequired)->minus($reservation->amountPaid);

        return BigDecimal::max($purpose === OnlinePaymentPurpose::Deposit ? BigDecimal::min($deposit, $owed) : $owed, BigDecimal::zero())->toScale(2);
    }
}
