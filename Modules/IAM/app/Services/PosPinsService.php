<?php

namespace Modules\IAM\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Exceptions\PosPinNotAllowed;
use Modules\IAM\Models\User;

/**
 * POS PINs are kept as HMAC-SHA256 of tenant and PIN with the app key: a lookup by value tells a PIN
 * already taken (unique per tenant), and the PIN cannot be read back without the key.
 */
class PosPinsService implements PosPins
{
    public function set(int $userId, string $pin): void
    {
        if (preg_match('/^\d{4,6}$/', $pin) !== 1) {
            throw new PosPinNotAllowed(__('The PIN must be 4 to 6 digits.'));
        }

        $user = User::query()->findOrFail($userId);
        $hash = $this->hash($user->tenant_id, $pin);

        if (User::query()->where('pos_pin', $hash)->whereKeyNot($user->id)->exists()) {
            throw new PosPinNotAllowed(__('Choose another PIN.'));
        }

        try {
            $user->forceFill(['pos_pin' => $hash, 'pos_pin_set_at' => now()])->save();
        } catch (UniqueConstraintViolationException) {
            throw new PosPinNotAllowed(__('Choose another PIN.'));
        }
    }

    public function clear(int $userId): void
    {
        User::query()->whereKey($userId)->update(['pos_pin' => null, 'pos_pin_set_at' => null]);
    }

    public function has(int $userId): bool
    {
        return User::query()->whereKey($userId)->whereNotNull('pos_pin')->exists();
    }

    public function matches(int $userId, string $pin): bool
    {
        $user = User::query()->find($userId);

        return $user instanceof User && $user->pos_pin !== null && preg_match('/^\d{4,6}$/', $pin) === 1
            && hash_equals($user->pos_pin, $this->hash($user->tenant_id, $pin));
    }

    private function hash(int $tenantId, string $pin): string
    {
        return hash_hmac('sha256', $tenantId.'|'.$pin, (string) config('app.key'));
    }
}
