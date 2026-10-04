<?php

namespace Modules\Billing\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Billing\Database\Factories\ExtraServiceFactory;

/**
 * An item of a property's extras catalogue (airport pickup, extra bed, laundry…) with its price
 * and charge code, posted to folios.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $charge_code_id
 * @property string $name
 * @property string|null $unit
 * @property string $unit_price
 * @property bool $price_includes_tax
 * @property bool $is_active
 * @property int $sort_order
 * @property-read ChargeCode $chargeCode
 */
#[UseFactory(ExtraServiceFactory::class)]
#[Fillable(['property_id', 'charge_code_id', 'name', 'unit', 'unit_price', 'price_includes_tax', 'is_active', 'sort_order'])]
class ExtraService extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ExtraServiceFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'price_includes_tax' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<ChargeCode, $this>
     */
    public function chargeCode(): BelongsTo
    {
        return $this->belongsTo(ChargeCode::class)->withTrashed();
    }
}
