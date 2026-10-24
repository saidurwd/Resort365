<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Models\PaymentGateway;

/**
 * Sets up a payment gateway for a property (Step 8.3): one per provider; credentials left empty keep what is stored (they are never
 * shown again); it can only be switched on with every credential filled in; the test gateway never in production.
 */
class SaveGateway extends Action
{
    /**
     * @param  array<string, string|null>  $credentials
     *
     * @throws ValidationException
     */
    public function handle(int $propertyId, GatewayProvider $provider, GatewayMode $mode, array $credentials, bool $enabled): PaymentGateway
    {
        if (! $provider->isAvailable()) {
            throw ValidationException::withMessages(['provider' => __('The test gateway cannot be used here.')]);
        }

        $gateway = PaymentGateway::query()->where('property_id', $propertyId)->where('provider', $provider->value)->first()
            ?? new PaymentGateway(['property_id' => $propertyId, 'provider' => $provider]);

        $stored = $gateway->credentials ?? [];

        foreach (array_keys($provider->credentialFields()) as $field) {
            $value = trim((string) ($credentials[$field] ?? ''));

            if ($value !== '') {
                $stored[$field] = $value;
            }
        }

        $gateway->fill(['mode' => $mode, 'credentials' => $stored, 'is_enabled' => $enabled]);

        if ($enabled && ! $gateway->isConfigured()) {
            throw ValidationException::withMessages(['credentials' => __('Fill in every credential before switching the gateway on.')]);
        }

        $gateway->save();

        return $gateway;
    }
}
