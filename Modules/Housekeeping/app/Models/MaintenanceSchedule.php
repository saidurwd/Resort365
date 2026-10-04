<?php

namespace Modules\Housekeeping\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Modules\Housekeeping\Database\Factories\MaintenanceScheduleFactory;
use Modules\Housekeeping\Enums\WorkOrderCategory;

/**
 * Preventive maintenance (ARCHITECTURE §5.11), e.g. AC servicing every 90 days: when next_due_on
 * comes, CreateDueWorkOrders opens a work order and moves next_due_on on by interval_days.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $title
 * @property WorkOrderCategory $category
 * @property int|null $room_id
 * @property string|null $location
 * @property int $interval_days
 * @property Carbon $next_due_on
 * @property int|null $assigned_to
 * @property bool $is_active
 * @property string|null $notes
 */
#[UseFactory(MaintenanceScheduleFactory::class)]
#[Fillable([
    'property_id', 'title', 'category', 'room_id', 'location', 'interval_days', 'next_due_on', 'assigned_to', 'is_active', 'notes',
])]
class MaintenanceSchedule extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<MaintenanceScheduleFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => WorkOrderCategory::class,
            'next_due_on' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
