<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Restaurant\Database\Factories\DiningTableFactory;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Enums\TableStatus;

/**
 * A table on an outlet's floor plan (ARCHITECTURE §5.10.3): number (unique per outlet), seats, shape and
 * position on its area's canvas (pos_x, pos_y: top-left corner, canvas units). status is the live
 * state the POS shows from Step 3.4.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $dining_area_id
 * @property string $number
 * @property int $seats
 * @property TableShape $shape
 * @property int $pos_x
 * @property int $pos_y
 * @property TableStatus $status
 * @property bool $is_active
 */
#[UseFactory(DiningTableFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'dining_area_id', 'number', 'seats', 'shape', 'pos_x', 'pos_y', 'status', 'is_active',
])]
class DiningTable extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<DiningTableFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shape' => TableShape::class,
            'status' => TableStatus::class,
            'is_active' => 'boolean',
        ];
    }
}
