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
use Modules\Inventory\Database\Factories\StockIssueFactory;

/**
 * A document for stock that left a store for a department or a purpose (Step 5.4): from a requisition, a direct issue, or a housekeeping task. Its lines are issue movements at the average cost.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $issue_no
 * @property int $store_id
 * @property int|null $department_id
 * @property int|null $requisition_id
 * @property string|null $source_type
 * @property int|null $source_id
 * @property string|null $reason
 * @property string|null $notes
 * @property Carbon $issued_on
 * @property int|null $issued_by
 * @property-read Store $store
 * @property-read Collection<int, StockIssueLine> $lines
 */
#[UseFactory(StockIssueFactory::class)]
#[Fillable(['property_id', 'issue_no', 'store_id', 'department_id', 'requisition_id', 'source_type', 'source_id', 'reason', 'notes', 'issued_on', 'issued_by'])]
class StockIssue extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<StockIssueFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issued_on' => 'date'];
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return HasMany<StockIssueLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(StockIssueLine::class)->orderBy('id');
    }
}
