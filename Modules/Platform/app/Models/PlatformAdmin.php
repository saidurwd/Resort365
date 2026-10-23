<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Modules\Platform\Database\Factories\PlatformAdminFactory;

/**
 * Platform super admin (central, not tenant-scoped). Signs in with the `platform` guard on the central domain.
 * Signs in with a second factor (an authenticator app's code, or a one-time recovery code) once set up; production requires it
 * (Phase 7 review). Impersonating tenant users is audited (Step 7.3).
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property string|null $two_factor_secret encrypted
 * @property string|null $two_factor_recovery_codes encrypted JSON list
 * @property Carbon|null $two_factor_confirmed_at
 */
#[UseFactory(PlatformAdminFactory::class)]
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class PlatformAdmin extends Authenticatable
{
    /** @use HasFactory<PlatformAdminFactory> */
    use HasFactory;

    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
