<?php

namespace Modules\Platform\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Platform\Database\Factories\SubscriptionFactory;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Enums\SubscriptionStatus;

/**
 * A tenant's subscription to a plan (central, Step 7.4): a trial first, then paid periods, each with a platform invoice. A tenant has at
 * most one that is not cancelled. Cancelling takes effect at the end of the trial or of the paid period.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plan_id
 * @property BillingPeriod $billing_period
 * @property SubscriptionStatus $status
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property bool $cancel_at_period_end
 * @property string|null $cancel_reason
 * @property Carbon|null $cancelled_at
 * @property string $gateway
 * @property string|null $gateway_reference
 * @property Carbon $created_at
 * @property-read Tenant $tenant
 * @property-read Plan $plan
 */
#[UseFactory(SubscriptionFactory::class)]
#[Fillable(['tenant_id', 'plan_id', 'billing_period', 'status', 'trial_ends_at', 'current_period_start', 'current_period_end', 'cancel_at_period_end', 'cancel_reason', 'cancelled_at', 'gateway', 'gateway_reference'])]
class Subscription extends Model
{
    /** @use HasFactory<SubscriptionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_period' => BillingPeriod::class, 'status' => SubscriptionStatus::class, 'trial_ends_at' => 'datetime', 'current_period_start' => 'date',
            'current_period_end' => 'date', 'cancel_at_period_end' => 'boolean', 'cancelled_at' => 'datetime',
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
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<PlatformInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(PlatformInvoice::class);
    }

    /** The plan's price for this subscription's billing period. */
    public function price(): string
    {
        return $this->billing_period === BillingPeriod::Yearly ? (string) $this->plan->yearly_price : (string) $this->plan->monthly_price;
    }

    /** When the trial or the paid period ends (the day after its last day), or null for a cancelled subscription. */
    public function endsAt(): ?Carbon
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trial_ends_at,
            SubscriptionStatus::Active => $this->current_period_end?->copy()->addDay(),
            SubscriptionStatus::Cancelled => null,
        };
    }

    /**
     * The tenant's subscription that is not cancelled, if any.
     */
    public static function currentFor(int $tenantId): ?self
    {
        return self::query()->where('tenant_id', $tenantId)->where('status', '!=', SubscriptionStatus::Cancelled->value)->latest('id')->first();
    }
}
