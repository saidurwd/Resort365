<?php

namespace Modules\Restaurant\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\WastageEntryFactory;
use Modules\Restaurant\Enums\WastageReason;

/**
 * Food or ingredients thrown away (Step 5.7): taken out of stock at cost.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $wastage_no
 * @property int $store_id
 * @property Carbon $wasted_on
 * @property WastageReason $reason
 * @property string|null $notes
 * @property string|null $source_type
 * @property int|null $source_id
 * @property int $recorded_by
 * @property string $total_cost
 * @property-read Collection<int, WastageLine> $lines
 */
#[UseFactory(WastageEntryFactory::class)]
#[Fillable(['property_id', 'wastage_no', 'store_id', 'wasted_on', 'reason', 'notes', 'source_type', 'source_id', 'recorded_by', 'total_cost'])]
class WastageEntry extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<WastageEntryFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['wasted_on' => 'date', 'reason' => WastageReason::class, 'total_cost' => 'decimal:2'];
    }

    /**
     * @return HasMany<WastageLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(WastageLine::class)->orderBy('id');
    }
}
