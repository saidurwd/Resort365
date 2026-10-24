<?php

namespace Modules\Billing\Services\Gateways;

use Modules\Billing\Contracts\GuestPaymentGateway;
use Modules\Billing\Enums\GatewayProvider;

/**
 * The adapter of each payment gateway provider (Step 8.3).
 */
class GatewayDrivers
{
    public function for(GatewayProvider $provider): GuestPaymentGateway
    {
        return match ($provider) {
            GatewayProvider::Sslcommerz => app(SslcommerzGateway::class),
            GatewayProvider::Bkash => app(BkashGateway::class),
            GatewayProvider::Stripe => app(StripeGateway::class),
            GatewayProvider::Test => app(TestGateway::class),
        };
    }

    /**
     * The URL with more query parameters.
     *
     * @param  array<string, string>  $query
     */
    public static function withQuery(string $url, array $query): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query($query);
    }
}
