<?php

namespace Modules\Platform\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\ImpersonationSessionFactory;

/**
 * A platform admin signed in as a tenant user (central, Step 7.3): why, when the one-time link was made, used and ended.
 *
 * @property int $id
 * @property int $platform_admin_id
 * @property int $tenant_id
 * @property int $user_id
 * @property string $user_name
 * @property string $reason
 * @property string $token_hash
 * @property Carbon $expires_at
 * @property Carbon|null $started_at
 * @property Carbon|null $ended_at
 * @property string|null $ip
 * @property-read PlatformAdmin $admin
 */
#[UseFactory(ImpersonationSessionFactory::class)]
#[Fillable(['platform_admin_id', 'tenant_id', 'user_id', 'user_name', 'reason', 'token_hash', 'expires_at', 'started_at', 'ended_at', 'ip'])]
class ImpersonationSession extends Model
{
    /** @use HasFactory<ImpersonationSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'started_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<PlatformAdmin, $this>
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(PlatformAdmin::class, 'platform_admin_id');
    }
}
