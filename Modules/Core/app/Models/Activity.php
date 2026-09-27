<?php

namespace Modules\Core\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Modules\Core\Database\Factories\ActivityFactory;
use Spatie\Activitylog\Models\Activity as SpatieActivity;

/**
 * An audit log entry of the current tenant (ARCHITECTURE §9.2).
 */
#[UseFactory(ActivityFactory::class)]
class Activity extends SpatieActivity
{
    use BelongsToTenant;

    /** @use HasFactory<ActivityFactory> */
    use HasFactory;
}
