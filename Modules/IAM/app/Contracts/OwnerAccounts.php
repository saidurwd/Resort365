<?php

namespace Modules\IAM\Contracts;

/**
 * The first person of a company that signed up by itself (Step 7.4), for Platform's sign-up. Both methods work in the current tenant.
 */
interface OwnerAccounts
{
    /**
     * Creates the active Tenant Owner with the password they chose and emails them the link to confirm their address.
     *
     * @return int the user's id
     */
    public function createOwner(string $name, string $email, string $password): int;

    /**
     * A one-time link that signs the user in on the tenant's subdomain and opens the setup checklist; it works once, within ten minutes.
     */
    public function welcomeLink(int $userId): string;
}
