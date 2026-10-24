<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Modules\Billing\DTOs\GatewayCheckout;
use Modules\Billing\DTOs\OnlinePaymentRequest;
use Modules\Billing\Enums\OnlinePaymentStatus;
use Modules\Billing\Exceptions\GatewayError;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Models\OnlinePayment;
use Modules\Billing\Models\PaymentGateway;
use Modules\Billing\Services\GatewayLog;
use Modules\Billing\Services\Gateways\GatewayDrivers;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Reservation\Contracts\ReservationLookup;

/**
 * Starts paying a booking online (Step 8.3): checks the gateway (enabled, configured, available) and the amount (more than nothing, at
 * most what the booking still owes), records the attempt, and asks the gateway for its payment page. The answer is where to send the
 * guest; the gateway sends them back to `/pay/return/{uuid}` and notifies `/pay/notify/{provider}/{gateway}`.
 */
class StartOnlinePayment extends Action
{
    public const int MINUTES = 60;

    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly GuestLookup $guests,
        private readonly PropertyDirectory $properties,
        private readonly GatewayDrivers $drivers,
        private readonly GatewayLog $log,
    ) {}

    /**
     * @return array{0: OnlinePayment, 1: string} the attempt and where to send the guest
     *
     * @throws PaymentNotAllowed
     */
    public function handle(OnlinePaymentRequest $request): array
    {
        $reservation = $this->reservations->find($request->reservationId) ?? throw new PaymentNotAllowed(__('Unknown reservation.'));

        if (! $reservation->acceptsPayments()) {
            throw new PaymentNotAllowed(__('Payments cannot be taken for a :status booking.', ['status' => strtolower($reservation->status->label())]));
        }

        $gateway = PaymentGateway::query()->where('property_id', $reservation->propertyId)->where('provider', $request->provider->value)->where('is_enabled', true)->first();

        if (! $gateway instanceof PaymentGateway || ! $gateway->isConfigured() || ! $gateway->provider->isAvailable()) {
            throw new PaymentNotAllowed(__('This way of paying is not available.'));
        }

        $amount = BigDecimal::of($request->amount)->toScale(2);
        $due = BigDecimal::of($reservation->grandTotal)->minus($reservation->amountPaid);

        if (! $amount->isPositive() || $amount->isGreaterThan($due)) {
            throw new PaymentNotAllowed(__('The amount must be more than zero and at most the balance due (:due).', ['due' => (string) BigDecimal::max($due, BigDecimal::zero())->toScale(2)]));
        }

        $payment = OnlinePayment::query()->create([
            'property_id' => $reservation->propertyId, 'uuid' => (string) Str::uuid(), 'payment_gateway_id' => $gateway->id, 'provider' => $gateway->provider,
            'reservation_id' => $reservation->id, 'purpose' => $request->purpose, 'amount' => (string) $amount, 'currency_code' => $reservation->currencyCode,
            'status' => OnlinePaymentStatus::Pending, 'return_url' => $request->returnUrl, 'expires_at' => now()->addMinutes(self::MINUTES),
        ]);

        $guest = $this->guests->find($reservation->primaryGuestId);
        $property = $this->properties->find($reservation->propertyId);
        $checkout = new GatewayCheckout(
            $payment->uuid, (string) $amount, $reservation->currencyCode,
            __('Booking :code at :property', ['code' => $reservation->code, 'property' => $property->name ?? config('app.name')]),
            $guest->name ?? $reservation->guestName, $guest?->email, $guest?->phone,
            route('billing.online.return', ['uuid' => $payment->uuid]),
            route('billing.online.notify', ['provider' => $gateway->provider->value, 'gateway' => $gateway->id]),
        );

        try {
            $start = $this->drivers->for($gateway->provider)->start($gateway->setup(), $checkout);
        } catch (GatewayError $error) {
            $payment->forceFill(['status' => OnlinePaymentStatus::Failed, 'failure_reason' => mb_substr($error->getMessage(), 0, 500)])->save();
            $this->log->record($payment->property_id, $gateway->provider, 'start', $payment, ['error' => $error->getMessage()], outcome: 'failed');

            throw new PaymentNotAllowed(__('The payment could not be started. Please try again or choose another way to pay.'), $error->getCode(), $error);
        }

        $payment->forceFill(['gateway_ref' => $start->gatewayRef])->save();
        $this->log->record($payment->property_id, $gateway->provider, 'start', $payment, ['gateway_ref' => $start->gatewayRef, 'amount' => (string) $amount], outcome: 'started');

        return [$payment, $start->redirectUrl];
    }
}
