<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Billing\Database\Factories\ChargeCodeFactory;
use Modules\Billing\Enums\ChargeCategory;

/**
 * What a folio charge is (ROOM, EXBED, LAUNDRY…), with its category for routing and its tax
 * category (Core's TaxEngine). Tenant-wide; new tenants get a default set.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property ChargeCategory $category
 * @property int|null $tax_category_id
 * @property bool $is_active
 * @property int $sort_order
 */
#[UseFactory(ChargeCodeFactory::class)]
#[Fillable(['code', 'name', 'category', 'tax_category_id', 'is_active', 'sort_order'])]
class ChargeCode extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<ChargeCodeFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['category' => ChargeCategory::class, 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }
}
