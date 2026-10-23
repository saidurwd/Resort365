<?php

namespace App\Support\Api;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Whether an API request may use a permission (Step 8.1): the token's user holds it and the token was given it (a token's abilities
 * are permission names). Used by the `api.can` middleware and by endpoints with an extra, conditional permission.
 */
final class ApiAbility
{
    public static function allows(?Authenticatable $user, string $permission): bool
    {
        return $user instanceof Authorizable && $user->can($permission) && self::tokenAllows($user, $permission);
    }

    public static function tokenAllows(?Authenticatable $user, string $permission): bool
    {
        return $user instanceof Authenticatable && method_exists($user, 'tokenCan') && $user->tokenCan($permission) === true;
    }
}
