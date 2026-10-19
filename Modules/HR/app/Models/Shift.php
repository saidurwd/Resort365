<?php

namespace Modules\HR\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Database\Factories\ShiftFactory;
use Modules\HR\DTOs\ShiftTimes;

/**
 * A working pattern (Step 6.2): start and end time (an end at or before the start runs past midnight), an unpaid break,
 * grace minutes for late arrival and early leave, and how long after the end counts as overtime.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string $starts_at
 * @property string $ends_at
 * @property int $break_minutes
 * @property int $grace_minutes
 * @property int $overtime_after_minutes
 * @property string $color
 * @property bool $is_active
 */
#[UseFactory(ShiftFactory::class)]
#[Fillable(['code', 'name', 'starts_at', 'ends_at', 'break_minutes', 'grace_minutes', 'overtime_after_minutes', 'color', 'is_active'])]
class Shift extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ShiftFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['break_minutes' => 'integer', 'grace_minutes' => 'integer', 'overtime_after_minutes' => 'integer', 'is_active' => 'boolean'];
    }

    public function times(): ShiftTimes
    {
        return new ShiftTimes(substr($this->starts_at, 0, 5), substr($this->ends_at, 0, 5), $this->break_minutes, $this->grace_minutes, $this->overtime_after_minutes);
    }

    public function crossesMidnight(): bool
    {
        return $this->times()->crossesMidnight();
    }

    /** "06:00–14:00". */
    public function window(): string
    {
        return substr($this->starts_at, 0, 5).'–'.substr($this->ends_at, 0, 5);
    }
}
