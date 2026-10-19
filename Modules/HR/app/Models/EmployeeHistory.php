<?php

namespace Modules\HR\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;
use Modules\HR\Database\Factories\EmployeeHistoryFactory;
use Modules\HR\Enums\HistoryEvent;

/**
 * One thing that happened to an employee (Step 6.1): joined, confirmation, transfer, promotion, increment,
 * disciplinary record, exit. Append-only; `details` holds the before and after values.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property HistoryEvent $event
 * @property Carbon $effective_on
 * @property array<string, mixed>|null $details
 * @property string|null $notes
 * @property int|null $recorded_by
 */
#[UseFactory(EmployeeHistoryFactory::class)]
#[Fillable(['employee_id', 'event', 'effective_on', 'details', 'notes', 'recorded_by'])]
class EmployeeHistory extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployeeHistoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['event' => HistoryEvent::class, 'effective_on' => 'date', 'details' => 'array'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('An employee history entry cannot be changed.'));
        static::deleting(fn () => throw new LogicException('An employee history entry cannot be deleted.'));
    }
}
