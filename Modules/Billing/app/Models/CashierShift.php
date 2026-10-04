<?php

namespace Modules\Billing\Models;

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
use Modules\Billing\Database\Factories\CashierShiftFactory;
use Modules\Billing\Enums\CashierShiftStatus;

/**
 * A cashier's shift at one property (ARCHITECTURE §5.9): opened with a cash float, every payment
 * and refund the cashier takes meanwhile belongs to it (ShiftRegister), and it is closed with a
 * cash count (CloseShift). expected = float + cash received − cash refunded; variance = counted −
 * expected. A closed shift never changes. open_user_id is set only while open (one per cashier).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $user_id
 * @property Carbon $business_date
 * @property Carbon $opened_at
 * @property string $opening_float
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $cash_received
 * @property string|null $cash_refunded
 * @property string|null $expected_cash
 * @property string|null $counted_cash
 * @property string|null $cash_variance
 * @property string|null $variance_reason
 * @property array<string, int>|null $denominations note value => count
 * @property CashierShiftStatus $status
 * @property int|null $open_user_id
 * @property-read Collection<int, Payment> $payments
 */
#[UseFactory(CashierShiftFactory::class)]
#[Fillable([
    'property_id', 'user_id', 'business_date', 'opened_at', 'opening_float', 'closed_at', 'closed_by', 'cash_received', 'cash_refunded',
    'expected_cash', 'counted_cash', 'cash_variance', 'variance_reason', 'denominations', 'status', 'open_user_id',
])]
class CashierShift extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<CashierShiftFactory> */
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
            'status' => CashierShiftStatus::class,
        ];
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOpen(): bool
    {
        return $this->status === CashierShiftStatus::Open;
    }
}
