<?php

namespace Modules\IAM\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\IAM\Database\Factories\LoginHistoryFactory;
use Modules\IAM\Enums\LoginEvent;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property LoginEvent $event
 * @property string|null $email
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon $created_at
 */
#[UseFactory(LoginHistoryFactory::class)]
#[Fillable([
    'user_id',
    'event',
    'email',
    'ip_address',
    'user_agent',
])]
class LoginHistory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<LoginHistoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => LoginEvent::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
