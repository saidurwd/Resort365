<?php

namespace Modules\Restaurant\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Restaurant\Database\Factories\KotLineFactory;
use Modules\Restaurant\Enums\KotStatus;

/**
 * An order line on a kitchen ticket, with how many.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $kot_id
 * @property int $pos_order_line_id
 * @property int $quantity
 * @property KotStatus $status
 */
#[UseFactory(KotLineFactory::class)]
#[Fillable([
    'property_id', 'kot_id', 'pos_order_line_id', 'quantity', 'status',
])]
class KotLine extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<KotLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => KotStatus::class,
        ];
    }

    /**
     * @return BelongsTo<PosOrderLine, $this>
     */
    public function line(): BelongsTo
    {
        return $this->belongsTo(PosOrderLine::class, 'pos_order_line_id');
    }
}
