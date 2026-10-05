<?php

namespace Modules\Restaurant\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Modules\Restaurant\Models\KitchenStation;

/**
 * A kitchen display signed in with its station's device token, as the `kds` guard returns it: used to
 * authorize the station's private channel (Step 3.5). It is not a person and has no password.
 */
final readonly class StationDisplay implements Authenticatable
{
    public function __construct(
        public KitchenStation $station,
    ) {}

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return 'station:'.$this->station->id;
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getRememberTokenName(): string
    {
        return '';
    }
}
