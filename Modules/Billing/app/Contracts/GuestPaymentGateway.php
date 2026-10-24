<?php

namespace Modules\Billing\Contracts;

use Illuminate\Http\Request;
use Modules\Billing\DTOs\GatewayCheckout;
use Modules\Billing\DTOs\GatewayNotice;
use Modules\Billing\DTOs\GatewayResult;
use Modules\Billing\DTOs\GatewaySetup;
use Modules\Billing\DTOs\GatewayStart;
use Modules\Billing\Exceptions\GatewayError;

/**
 * A payment gateway for guests (Step 8.3, ARCHITECTURE §5.9): one adapter per provider (SSLCommerz, bKash, Stripe, the test
 * gateway). Not Platform's PaymentGateway, which collects the SaaS subscription.
 */
interface GuestPaymentGateway
{
    /**
     * Starts a payment: where to send the guest and the gateway's reference.
     *
     * @throws GatewayError
     */
    public function start(GatewaySetup $setup, GatewayCheckout $checkout): GatewayStart;

    /**
     * Asks the gateway what became of a payment, from what came back with the guest or a notification (never trusting it alone).
     *
     * @param  array<string, mixed>  $input
     *
     * @throws GatewayError
     */
    public function confirm(GatewaySetup $setup, string $reference, ?string $gatewayRef, array $input): GatewayResult;

    /**
     * A notification (IPN, webhook) with a valid signature, or null when the signature does not check out or it is not about a payment.
     */
    public function notice(GatewaySetup $setup, Request $request): ?GatewayNotice;

    /**
     * Refunds part or all of a payment.
     *
     * @throws GatewayError
     */
    public function refund(GatewaySetup $setup, string $gatewayRef, string $transactionId, string $amount, string $currency, string $reason): GatewayResult;
}
