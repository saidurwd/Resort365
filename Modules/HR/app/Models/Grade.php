<?php

namespace Modules\HR\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\HR\Database\Factories\GradeFactory;

/**
 * A pay or seniority grade (Step 6.1); a higher level is more senior. Salary structures attach to it in Step 6.4.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property int $level
 * @property bool $is_active
 */
#[UseFactory(GradeFactory::class)]
#[Fillable(['code', 'name', 'level', 'is_active'])]
class Grade extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<GradeFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['level' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
