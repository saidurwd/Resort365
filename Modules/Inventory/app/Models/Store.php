<?php

namespace Modules\Inventory\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Inventory\Database\Factories\StoreFactory;
use Modules\Inventory\Enums\StoreKind;

/**
 * A store of a property (Step 5.2): main, kitchen, bar, housekeeping or maintenance, tied to a department. Restaurant outlets draw from a store.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $code
 * @property string $name
 * @property StoreKind $kind
 * @property int|null $department_id
 * @property bool $is_active
 */
#[UseFactory(StoreFactory::class)]
#[Fillable(['property_id', 'code', 'name', 'kind', 'department_id', 'is_active'])]
class Store extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => StoreKind::class, 'is_active' => 'boolean'];
    }
}
