<?php

namespace Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Payroll\Database\Factories\PoolParticipantFactory;

/**
 * Whether an employee shares in the service charge and tips pools, and with how many points (Step 6.6). An employee with no
 * row takes part with one point.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property bool $is_eligible
 * @property string $points
 */
#[UseFactory(PoolParticipantFactory::class)]
#[Fillable(['employee_id', 'is_eligible', 'points'])]
class PoolParticipant extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PoolParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_eligible' => 'boolean', 'points' => 'decimal:2'];
    }
}
