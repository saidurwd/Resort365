<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Restaurant\Database\Factories\KitchenStationFactory;
use Modules\Restaurant\Enums\StationOutput;

/**
 * Where an outlet's dishes are prepared (hot kitchen, grill, pastry, bar…): orders are split into one
 * ticket per station (Step 3.5), shown on its kitchen display, printed, or both (ARCHITECTURE §5.10.6).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property string $name
 * @property StationOutput $output
 * @property int|null $printer_id
 * @property int $sort_order
 */
#[UseFactory(KitchenStationFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'name', 'output', 'printer_id', 'sort_order',
])]
class KitchenStation extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<KitchenStationFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'output' => StationOutput::class,
        ];
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}
