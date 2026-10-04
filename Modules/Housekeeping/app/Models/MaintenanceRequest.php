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
use Modules\Housekeeping\Database\Factories\MaintenanceRequestFactory;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Enums\WorkOrderStatus;

/**
 * A maintenance work order (ARCHITECTURE §5.11): reported by any staff member, prioritised, assigned
 * to a technician, worked on and closed with the labour and parts cost. Parts will be issued from
 * inventory once the Inventory module exists.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int|null $room_id
 * @property string|null $location
 * @property string $title
 * @property string|null $description
 * @property WorkOrderCategory $category
 * @property WorkOrderPriority $priority
 * @property WorkOrderStatus $status
 * @property int|null $reported_by
 * @property int|null $assigned_to
 * @property int|null $maintenance_schedule_id
 * @property Carbon|null $due_on
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property string $labour_cost
 * @property string $parts_cost
 * @property string|null $resolution
 */
#[UseFactory(MaintenanceRequestFactory::class)]
#[Fillable([
    'property_id', 'room_id', 'location', 'title', 'description', 'category', 'priority', 'status', 'reported_by', 'assigned_to', 'maintenance_schedule_id',
    'due_on', 'started_at', 'completed_at', 'labour_cost', 'parts_cost', 'resolution',
])]
class MaintenanceRequest extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<MaintenanceRequestFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => WorkOrderCategory::class,
            'priority' => WorkOrderPriority::class,
            'status' => WorkOrderStatus::class,
            'due_on' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'labour_cost' => 'decimal:2',
            'parts_cost' => 'decimal:2',
        ];
    }
}
