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
use Modules\Inventory\Database\Factories\StockTransferFactory;
use Modules\Inventory\Enums\TransferStatus;

/**
 * Stock sent from one store to another of the same property in two steps (Step 5.3): dispatched, then received (or cancelled and returned).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $transfer_no
 * @property int $from_store_id
 * @property int $to_store_id
 * @property TransferStatus $status
 * @property string|null $notes
 * @property Carbon $dispatched_at
 * @property int|null $dispatched_by
 * @property Carbon|null $received_at
 * @property int|null $received_by
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property-read Store $from
 * @property-read Store $to
 * @property-read Collection<int, StockTransferLine> $lines
 */
#[UseFactory(StockTransferFactory::class)]
#[Fillable(['property_id', 'transfer_no', 'from_store_id', 'to_store_id', 'status', 'notes', 'dispatched_at', 'dispatched_by', 'received_at', 'received_by', 'cancelled_at', 'cancelled_by'])]
class StockTransfer extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockTransferFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status' => TransferStatus::class, 'dispatched_at' => 'datetime', 'received_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function from(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'from_store_id');
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function to(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'to_store_id');
    }

    /**
     * @return HasMany<StockTransferLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockTransferLine::class)->orderBy('id');
    }
}
