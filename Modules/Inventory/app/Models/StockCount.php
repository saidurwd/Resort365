<?php

namespace Modules\Inventory\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Inventory\Database\Factories\StockCountFactory;
use Modules\Inventory\Enums\CountStatus;

/**
 * A physical count of a store (Step 5.4): the system quantities are snapshotted when it starts, counted quantities are entered, and completing it adjusts the differences.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $count_no
 * @property int $store_id
 * @property int|null $item_category_id
 * @property CountStatus $status
 * @property bool $blind
 * @property string|null $notes
 * @property Carbon $started_at
 * @property int|null $started_by
 * @property Carbon|null $completed_at
 * @property int|null $completed_by
 * @property-read Store $store
 * @property-read Collection<int, StockCountLine> $lines
 */
#[UseFactory(StockCountFactory::class)]
#[Fillable(['property_id', 'count_no', 'store_id', 'item_category_id', 'status', 'blind', 'notes', 'started_at', 'started_by', 'completed_at', 'completed_by'])]
class StockCount extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockCountFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => CountStatus::class, 'blind' => 'boolean', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<StockCountLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockCountLine::class)->orderBy('id');
    }
}
