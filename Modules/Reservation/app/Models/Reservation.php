<?php

namespace Modules\Reservation\Models;

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
use Modules\Reservation\Database\Factories\ReservationFactory;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * A booking (ARCHITECTURE §6.4, §8.3): items, guests and money in the property's currency.
 * Created by CreateReservation; cancelled, never deleted.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $code
 * @property ReservationStatus $status
 * @property PaymentStatus $payment_status
 * @property ReservationSource $source
 * @property int $primary_guest_id
 * @property int|null $company_id
 * @property int|null $travel_agent_id
 * @property int $rate_plan_id
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property int $adults
 * @property int $children
 * @property string $currency_code
 * @property string $exchange_rate
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $grand_total
 * @property int|null $deposit_policy_id
 * @property string $deposit_percent
 * @property string $deposit_required
 * @property Carbon|null $deposit_due_at
 * @property bool $auto_cancel_unpaid
 * @property int|null $deposit_override_by
 * @property string $amount_paid
 * @property string $balance_due
 * @property Carbon|null $balance_due_on
 * @property int|null $cancellation_policy_id
 * @property string|null $promo_code
 * @property int|null $promotion_id
 * @property string|null $special_requests
 * @property string|null $internal_notes
 * @property int|null $created_by
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $checked_out_at
 * @property string|null $cancellation_reason
 * @property string|null $cancellation_fee
 * @property Carbon|null $created_at
 * @property-read Collection<int, ReservationItem> $items
 * @property-read Collection<int, ReservationGuest> $guests
 * @property-read Collection<int, ReservationLog> $logs
 */
#[UseFactory(ReservationFactory::class)]
#[Fillable([
    'property_id', 'code', 'status', 'payment_status', 'source', 'primary_guest_id', 'company_id', 'travel_agent_id', 'rate_plan_id',
    'check_in', 'check_out', 'adults', 'children', 'currency_code', 'exchange_rate', 'subtotal', 'discount_total', 'tax_total', 'grand_total',
    'deposit_policy_id', 'deposit_percent', 'deposit_required', 'deposit_due_at', 'auto_cancel_unpaid', 'deposit_override_by', 'amount_paid',
    'balance_due', 'balance_due_on', 'cancellation_policy_id', 'promo_code', 'promotion_id', 'special_requests', 'internal_notes', 'created_by',
])]
class Reservation extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<ReservationFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReservationStatus::class,
            'payment_status' => PaymentStatus::class,
            'source' => ReservationSource::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'exchange_rate' => 'decimal:8',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'deposit_required' => 'decimal:2',
            'deposit_due_at' => 'datetime',
            'auto_cancel_unpaid' => 'boolean',
            'amount_paid' => 'decimal:2',
            'balance_due' => 'decimal:2',
            'balance_due_on' => 'date',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cancellation_fee' => 'decimal:2',
            'checked_in_at' => 'datetime',
            'checked_out_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ReservationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ReservationItem::class);
    }

    /**
     * @return HasMany<ReservationGuest, $this>
     */
    public function guests(): HasMany
    {
        return $this->hasMany(ReservationGuest::class);
    }

    /**
     * @return HasMany<InventoryLock, $this>
     */
    public function locks(): HasMany
    {
        return $this->hasMany(InventoryLock::class);
    }

    /**
     * @return HasMany<ReservationLog, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ReservationLog::class)->latest('id');
    }

    /**
     * Whether the stay, guests and deposit may still be changed or the booking cancelled
     * (before check-in; in-house changes come with the front office).
     */
    public function isChangeable(): bool
    {
        return in_array($this->status, [ReservationStatus::Tentative, ReservationStatus::Confirmed], true);
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }
}
