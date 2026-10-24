<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\DTOs\OnlinePaymentOption;
use Modules\Billing\DTOs\OnlinePaymentRequest;
use Modules\Billing\Exceptions\PaymentNotAllowed;

/**
 * Online payments for other modules (Step 8.3; the public website): which gateways a property takes, and starting a payment of a
 * booking. The payment confirms itself when the gateway says so (Billing records it and fires PaymentReceived).
 */
interface OnlinePayments
{
    /**
     * @return list<OnlinePaymentOption> the property's enabled, configured gateways
     */
    public function options(int $propertyId): array;

    /**
     * Starts the payment and answers where to send the guest.
     *
     * @throws PaymentNotAllowed when the gateway is not available, the amount is wrong or the gateway refuses
     */
    public function start(OnlinePaymentRequest $request): string;
}
