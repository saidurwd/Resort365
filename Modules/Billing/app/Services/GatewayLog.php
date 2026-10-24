<?php

namespace Modules\Billing\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Models\GatewayEvent;
use Modules\Billing\Models\OnlinePayment;

/**
 * Writes the gateway log (Step 8.3). Secrets never reach it: adapters strip their responses, and keys that look secret are removed
 * here too. A notification's event id is unique: recording it again answers false (a replay).
 */
class GatewayLog
{
    private const array SECRET_KEYS = ['store_passwd', 'store_password', 'password', 'app_secret', 'secret', 'secret_key', 'webhook_secret', 'id_token', 'card_no', 'verify_sign', 'sig'];

    /**
     * @param  array<string, mixed>  $payload
     * @return bool false when an event with this id was logged before (a replay)
     */
    public function record(int $propertyId, GatewayProvider $provider, string $kind, ?OnlinePayment $payment, array $payload = [], ?string $eventId = null, ?bool $signatureValid = null, ?string $outcome = null): bool
    {
        try {
            GatewayEvent::query()->create([
                'property_id' => $propertyId, 'online_payment_id' => $payment?->id, 'provider' => $provider, 'kind' => $kind, 'event_id' => $eventId,
                'signature_valid' => $signatureValid, 'outcome' => $outcome, 'payload' => $this->strip($payload),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    public function seen(GatewayProvider $provider, string $eventId): bool
    {
        return GatewayEvent::query()->where('provider', $provider->value)->where('event_id', $eventId)->exists();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function strip(array $payload): array
    {
        $clean = [];

        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), self::SECRET_KEYS, true)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->strip($value) : (is_scalar($value) || $value === null ? $value : (string) json_encode($value));
        }

        return $clean;
    }
}
