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
use Modules\Reservation\Database\Factories\QuoteFactory;
use Modules\Reservation\Enums\QuoteStatus;
use Modules\Reservation\Enums\ReservationSource;

/**
 * A saved price proposal (ARCHITECTURE §5.6), made in the booking wizard by SaveQuote and booked
 * at its prices by ConvertQuote. Amounts are in the property's currency.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $code
 * @property QuoteStatus $status
 * @property ReservationSource $source
 * @property int $guest_id
 * @property int|null $company_id
 * @property int|null $travel_agent_id
 * @property int $rate_plan_id
 * @property Carbon $check_in
 * @property Carbon $check_out
 * @property int $adults
 * @property int $children
 * @property string $currency_code
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_total
 * @property string $grand_total
 * @property string $deposit_percent
 * @property string $deposit_amount
 * @property string|null $promo_code
 * @property int|null $promotion_id
 * @property Carbon $valid_until
 * @property string|null $special_requests
 * @property string|null $internal_notes
 * @property int|null $reservation_id
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property int|null $created_by
 * @property-read Collection<int, QuoteItem> $items
 */
#[UseFactory(QuoteFactory::class)]
#[Fillable([
    'property_id', 'code', 'status', 'source', 'guest_id', 'company_id', 'travel_agent_id', 'rate_plan_id', 'check_in', 'check_out',
    'adults', 'children', 'currency_code', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'deposit_percent', 'deposit_amount',
    'promo_code', 'promotion_id', 'valid_until', 'special_requests', 'internal_notes', 'created_by',
])]
class Quote extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<QuoteFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'source' => ReservationSource::class,
            'check_in' => 'date',
            'check_out' => 'date',
            'adults' => 'integer',
            'children' => 'integer',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'deposit_percent' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<QuoteItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function nights(): int
    {
        return (int) $this->check_in->diffInDays($this->check_out);
    }

    /**
     * The status as of today: an open quote past its validity date counts as expired.
     */
    public function currentStatus(?Carbon $today = null): QuoteStatus
    {
        $today ??= Carbon::today();

        return $this->status->isOpen() && $this->valid_until->lt($today) ? QuoteStatus::Expired : $this->status;
    }
}
