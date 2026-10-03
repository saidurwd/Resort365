<?php

namespace Modules\Rates\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToProperty;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Rates\Database\Factories\CancellationPolicyFactory;
use Modules\Rates\DTOs\CancellationRuleData;
use Modules\Rates\DTOs\CancellationTerms;
use Modules\Rates\Enums\CancellationChargeType;

/**
 * Tiered cancellation charges by days before arrival, plus a no-show charge (ARCHITECTURE §5.5).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $property_id
 * @property string $name
 * @property string|null $description
 * @property CancellationChargeType|null $no_show_charge_type
 * @property string|null $no_show_charge_value
 * @property bool $is_default
 * @property-read Collection<int, CancellationPolicyRule> $rules
 */
#[UseFactory(CancellationPolicyFactory::class)]
#[Fillable(['property_id', 'name', 'description', 'no_show_charge_type', 'no_show_charge_value', 'is_default'])]
class CancellationPolicy extends Model
{
    use BelongsToProperty;
    use BelongsToTenant;

    /** @use HasFactory<CancellationPolicyFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'no_show_charge_type' => CancellationChargeType::class,
            'no_show_charge_value' => 'decimal:2',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Tiers from the furthest from arrival to the closest.
     *
     * @return HasMany<CancellationPolicyRule, $this>
     */
    public function rules(): HasMany
    {
        return $this->hasMany(CancellationPolicyRule::class)->orderByDesc('days_before_from');
    }

    public function terms(): CancellationTerms
    {
        return new CancellationTerms(
            $this->rules->map(fn (CancellationPolicyRule $rule): CancellationRuleData => new CancellationRuleData(
                $rule->days_before_from, $rule->days_before_to, $rule->charge_type, $rule->charge_value,
            ))->values()->all(),
            $this->no_show_charge_type,
            $this->no_show_charge_value,
        );
    }
}
