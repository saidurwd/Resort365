<?php

namespace Modules\Platform\Models;

use App\Models\Tenant;
use Brick\Math\BigDecimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\PlatformInvoiceFactory;
use Modules\Platform\Enums\PlatformInvoiceStatus;

/**
 * What the platform bills a tenant for one subscription period (central, Step 7.4), numbered `INV-2026-00001` per year without gaps.
 * Open until paid in full or voided; an open invoice past its due date suspends the tenant.
 *
 * @property int $id
 * @property string $number
 * @property int $tenant_id
 * @property int|null $subscription_id
 * @property PlatformInvoiceStatus $status
 * @property string $description
 * @property Carbon|null $period_start
 * @property Carbon|null $period_end
 * @property Carbon $issued_on
 * @property Carbon $due_on
 * @property string $currency_code
 * @property string $amount
 * @property string $paid_amount
 * @property Carbon|null $paid_at
 * @property Carbon|null $voided_at
 * @property string|null $void_reason
 * @property-read Tenant $tenant
 */
#[UseFactory(PlatformInvoiceFactory::class)]
#[Fillable(['number', 'tenant_id', 'subscription_id', 'status', 'description', 'period_start', 'period_end', 'issued_on', 'due_on', 'currency_code', 'amount', 'paid_amount', 'paid_at', 'voided_at', 'void_reason'])]
class PlatformInvoice extends Model
{
    /** @use HasFactory<PlatformInvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PlatformInvoiceStatus::class, 'period_start' => 'date', 'period_end' => 'date', 'issued_on' => 'date', 'due_on' => 'date',
            'amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'paid_at' => 'datetime', 'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * @return HasMany<PlatformPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(PlatformPayment::class);
    }

    /** What is still to pay, as a decimal string. */
    public function balance(): string
    {
        return (string) BigDecimal::of($this->amount)->minus($this->paid_amount)->toScale(2);
    }

    /** Whether it is open and its due date has passed. */
    public function isOverdue(?Carbon $today = null): bool
    {
        return $this->status === PlatformInvoiceStatus::Open && $this->due_on->lt(($today ?? now())->startOfDay());
    }
}
