<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\ImpersonationGrant;

/**
 * The tenant side of a platform admin signing in as a tenant user (Step 7.3, audited). Platform issues the one-time links and keeps
 * the record; IAM redeems a link, signs the user in and shows the banner. A link works once, for a minute, on the tenant it was made
 * for.
 */
interface Impersonation
{
    /**
     * Redeems the link's token for the current tenant; null when it is unknown, used, expired or for another tenant.
     */
    public function redeem(string $token): ?ImpersonationGrant;

    /**
     * Ends the session: the admin has signed out of the tenant.
     */
    public function end(int $sessionId): void;

    /**
     * Where the admin goes after leaving (the platform console).
     */
    public function exitUrl(): string;
}
