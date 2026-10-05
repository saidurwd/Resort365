<?php

namespace Modules\IAM\Contracts;

use Modules\IAM\Exceptions\PosPinNotAllowed;

/**
 * Staff POS PINs (ARCHITECTURE §3.3 rule 6): 4–6 digits, unique per tenant, stored as a keyed hash.
 * The Restaurant POS signs a person in with one on a registered terminal, and checks a manager's
 * PIN for approvals.
 */
interface PosPins
{
    /**
     * @throws PosPinNotAllowed when the PIN is not 4–6 digits or someone else has it
     */
    public function set(int $userId, string $pin): void;

    public function clear(int $userId): void;

    public function has(int $userId): bool;

    public function matches(int $userId, string $pin): bool;
}
