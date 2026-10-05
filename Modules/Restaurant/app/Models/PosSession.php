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
use Illuminate\Support\Carbon;
use Modules\Restaurant\Database\Factories\PosSessionFactory;
use Modules\Restaurant\Enums\PosSessionStatus;

/**
 * A cashier's session on a POS terminal (ARCHITECTURE §5.10.11): opened with a float on the business
 * date, every POS payment belongs to it (Step 3.6), closed with a cash count; the variance needs a
 * reason, and above the property's limit a manager's approval. A closed session never changes.
 * open_terminal_id is set only while open (one per terminal).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $outlet_id
 * @property int $pos_terminal_id
 * @property Carbon $business_date
 * @property int $opened_by
 * @property Carbon $opened_at
 * @property string $opening_float
 * @property int|null $closed_by
 * @property Carbon|null $closed_at
 * @property string|null $cash_received
 * @property string|null $cash_refunded
 * @property string|null $expected_cash
 * @property string|null $counted_cash
 * @property string|null $cash_variance
 * @property string|null $variance_reason
 * @property array<string, int>|null $denominations
 * @property int|null $manager_approval_id
 * @property PosSessionStatus $status
 * @property int|null $open_terminal_id
 * @property-read Outlet $outlet
 * @property-read PosTerminal $terminal
 */
#[UseFactory(PosSessionFactory::class)]
#[Fillable([
    'property_id', 'outlet_id', 'pos_terminal_id', 'business_date', 'opened_by', 'opened_at', 'opening_float', 'closed_by', 'closed_at', 'cash_received',
    'cash_refunded', 'expected_cash', 'counted_cash', 'cash_variance', 'variance_reason', 'denominations', 'manager_approval_id', 'status', 'open_terminal_id',
])]
class PosSession extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<PosSessionFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_float' => 'decimal:2',
            'cash_received' => 'decimal:2',
            'cash_refunded' => 'decimal:2',
            'expected_cash' => 'decimal:2',
            'counted_cash' => 'decimal:2',
            'cash_variance' => 'decimal:2',
            'denominations' => 'array',
            'status' => PosSessionStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Outlet, $this>
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * @return BelongsTo<PosTerminal, $this>
     */
    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'pos_terminal_id');
    }

    public function isOpen(): bool
    {
        return $this->status === PosSessionStatus::Open;
    }
}
