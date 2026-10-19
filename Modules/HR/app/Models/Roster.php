<?php

namespace Modules\HR\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\HR\Database\Factories\RosterFactory;

/**
 * Which shift an employee works on a date (Step 6.2); no shift means a day off. One row per employee and date.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $employee_id
 * @property Carbon $work_date
 * @property int|null $shift_id
 * @property-read Shift|null $shift
 */
#[UseFactory(RosterFactory::class)]
#[Fillable(['property_id', 'employee_id', 'work_date', 'shift_id'])]
class Roster extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<RosterFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['work_date' => 'date'];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Shift, $this>
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class)->withTrashed();
    }
}
