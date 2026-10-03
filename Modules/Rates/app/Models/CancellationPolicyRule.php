<?php

namespace Modules\Rates\Models;

use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Rates\Database\Factories\CancellationPolicyRuleFactory;
use Modules\Rates\Enums\CancellationChargeType;

/**
 * One tier: cancelling between days_before_from and days_before_to days before arrival (to null =
 * that many days or more) charges charge_value of charge_type. Audited on the policy (SaveCancellationPolicy).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property int $cancellation_policy_id
 * @property int $days_before_from
 * @property int|null $days_before_to
 * @property CancellationChargeType $charge_type
 * @property string $charge_value
 */
#[UseFactory(CancellationPolicyRuleFactory::class)]
#[Fillable(['property_id', 'cancellation_policy_id', 'days_before_from', 'days_before_to', 'charge_type', 'charge_value'])]
class CancellationPolicyRule extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<CancellationPolicyRuleFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'days_before_from' => 'integer',
            'days_before_to' => 'integer',
            'charge_type' => CancellationChargeType::class,
            'charge_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<CancellationPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(CancellationPolicy::class, 'cancellation_policy_id');
    }

    /**
     * E.g. "7–14 days before arrival", "15 or more days before arrival", "on the arrival day".
     */
    public function windowLabel(): string
    {
        return match (true) {
            $this->days_before_to === null => __(':from or more days before arrival', ['from' => $this->days_before_from]),
            $this->days_before_from === 0 && $this->days_before_to === 0 => __('On the arrival day'),
            default => __(':from–:to days before arrival', ['from' => $this->days_before_from, 'to' => $this->days_before_to]),
        };
    }
}
