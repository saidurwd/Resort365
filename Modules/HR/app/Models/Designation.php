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
use Modules\HR\Database\Factories\DesignationFactory;

/**
 * A job title (Step 6.1), optionally tied to a department, shared by the tenant's properties.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int|null $department_id
 * @property bool $is_active
 */
#[UseFactory(DesignationFactory::class)]
#[Fillable(['name', 'department_id', 'is_active'])]
class Designation extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DesignationFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
