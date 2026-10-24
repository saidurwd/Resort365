<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The payment gateways guests can pay through (Step 8.3, Q4): SSLCommerz and bKash for Bangladesh, Stripe for tenants in countries
 * Stripe supports, and a test gateway for local and staging use only.
 */
enum GatewayProvider: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Sslcommerz = 'sslcommerz';
    case Bkash = 'bkash';
    case Stripe = 'stripe';
    case Test = 'test';

    public function label(): string
    {
        return match ($this) {
            self::Sslcommerz => __('SSLCommerz (cards, mobile banking, internet banking)'),
            self::Bkash => __('bKash'),
            self::Stripe => __('Stripe (international cards)'),
            self::Test => __('Test gateway (not for real payments)'),
        };
    }

    /** The short name guests see on the payment buttons. */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Sslcommerz => __('Card or mobile banking'),
            self::Bkash => 'bKash',
            self::Stripe => __('International card'),
            self::Test => __('Test payment'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sslcommerz => 'primary',
            self::Bkash => 'danger',
            self::Stripe => 'info',
            self::Test => 'secondary',
        };
    }

    /**
     * The credentials the gateway needs: field => [label, secret].
     *
     * @return array<string, array{0: string, 1: bool}>
     */
    public function credentialFields(): array
    {
        return match ($this) {
            self::Sslcommerz => ['store_id' => [__('Store ID'), false], 'store_password' => [__('Store password'), true]],
            self::Bkash => ['app_key' => [__('App key'), false], 'app_secret' => [__('App secret'), true], 'username' => [__('Username'), false], 'password' => [__('Password'), true]],
            self::Stripe => ['secret_key' => [__('Secret key'), true], 'webhook_secret' => [__('Webhook signing secret'), true]],
            self::Test => [],
        };
    }

    /**
     * The hosts a browser is sent to (the Content-Security-Policy's form-action) for the mode.
     *
     * @return list<string>
     */
    public function hosts(GatewayMode $mode): array
    {
        return match ($this) {
            self::Sslcommerz => [$mode === GatewayMode::Live ? 'https://securepay.sslcommerz.com' : 'https://sandbox.sslcommerz.com'],
            self::Bkash => $mode === GatewayMode::Live ? ['https://payment.bkash.com', 'https://tokenized.pay.bka.sh'] : ['https://sandbox.payment.bkash.com', 'https://tokenized.sandbox.bka.sh'],
            self::Stripe => ['https://checkout.stripe.com'],
            self::Test => [],
        };
    }

    /** Whether the gateway can be used in this installation (the test gateway never in production). */
    public function isAvailable(): bool
    {
        return $this !== self::Test || ! app()->isProduction();
    }
}
