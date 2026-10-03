<?php

namespace Modules\Rates\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Rates\Database\Factories\DepositPolicyFactory;
use Modules\Rates\DTOs\DepositTerms;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\DepositType;

/**
 * How much advance a booking needs and by when (ARCHITECTURE §6.5). The percentage is negotiable
 * per booking (Q7): default_percent is suggested, min/max are optional limits.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $name
 * @property DepositType $type
 * @property string|null $min_percent
 * @property string $default_percent
 * @property string|null $max_percent
 * @property string|null $fixed_amount
 * @property int $due_within_minutes
 * @property bool $auto_cancel_unpaid
 * @property BalanceDueRule $balance_due_rule
 * @property int|null $balance_due_days
 * @property int|null $full_payment_within_hours
 * @property bool $is_default
 */
#[UseFactory(DepositPolicyFactory::class)]
#[Fillable([
    'property_id', 'name', 'type', 'min_percent', 'default_percent', 'max_percent', 'fixed_amount', 'due_within_minutes',
    'auto_cancel_unpaid', 'balance_due_rule', 'balance_due_days', 'full_payment_within_hours', 'is_default',
])]
class DepositPolicy extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<DepositPolicyFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DepositType::class,
            'min_percent' => 'decimal:2',
            'default_percent' => 'decimal:2',
            'max_percent' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'due_within_minutes' => 'integer',
            'auto_cancel_unpaid' => 'boolean',
            'balance_due_rule' => BalanceDueRule::class,
            'balance_due_days' => 'integer',
            'full_payment_within_hours' => 'integer',
            'is_default' => 'boolean',
        ];
    }

    public function terms(): DepositTerms
    {
        return new DepositTerms($this->type, $this->min_percent, $this->default_percent, $this->max_percent, $this->fixed_amount,
            $this->due_within_minutes, $this->auto_cancel_unpaid, $this->balance_due_rule, $this->balance_due_days, $this->full_payment_within_hours);
    }
}
