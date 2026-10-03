<?php

namespace App\Support\Tenancy;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Which properties (resorts) a user may work in (ARCHITECTURE §3.3, §4.2).
 * Implemented by the Property module.
 */
interface PropertyAccess
{
    /**
     * The user's accessible properties in the current tenant, id => name, ordered by name.
     *
     * @return array<int, string>
     */
    public function accessibleProperties(Authenticatable $user): array;
}
