<?php

namespace Modules\Billing\Services;

use Modules\Billing\Actions\StartOnlinePayment;
use Modules\Billing\Contracts\OnlinePayments;
use Modules\Billing\DTOs\OnlinePaymentOption;
use Modules\Billing\DTOs\OnlinePaymentRequest;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Models\PaymentGateway;

class OnlinePaymentsService implements OnlinePayments
{
    public function __construct(private readonly StartOnlinePayment $start) {}

    public function options(int $propertyId): array
    {
        return PaymentGateway::query()->where('property_id', $propertyId)->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')->get()
            ->filter(fn (PaymentGateway $gateway): bool => $gateway->isConfigured() && $gateway->provider->isAvailable())
            ->map(fn (PaymentGateway $gateway): OnlinePaymentOption => new OnlinePaymentOption($gateway->provider, $gateway->provider->shortLabel(), $gateway->mode === GatewayMode::Sandbox))
            ->values()->all();
    }

    public function start(OnlinePaymentRequest $request): string
    {
        return $this->start->handle($request)[1];
    }
}
