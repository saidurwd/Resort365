<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToTenant;
use Database\Factories\DatabaseNotificationFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\DatabaseNotification as BaseDatabaseNotification;

/**
 * An in-app notification (database channel) with tenant_id. Notifiable models return it from
 * notifications() so the channel stores the current tenant (see Modules\IAM\Models\User).
 */
#[UseFactory(DatabaseNotificationFactory::class)]
class DatabaseNotification extends BaseDatabaseNotification
{
    use BelongsToTenant;

    /** @use HasFactory<DatabaseNotificationFactory> */
    use HasFactory;
}
