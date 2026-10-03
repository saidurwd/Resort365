<?php

namespace Modules\Property\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Property\Database\Factories\DepartmentFactory;

/**
 * A department of the tenant, e.g. Front Office or Housekeeping (ARCHITECTURE §5.4). HR,
 * Inventory and Accounting use departments as cost centres.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int $sort_order
 */
#[UseFactory(DepartmentFactory::class)]
#[Fillable(['code', 'name', 'description', 'is_active', 'sort_order'])]
class Department extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
