<?php

namespace Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Payroll\Database\Factories\SalaryStructureComponentFactory;
use Modules\Payroll\Enums\CalcType;

/**
 * One line of a salary structure (Step 6.4): a component and how its amount is worked out.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $salary_structure_id
 * @property int $salary_component_id
 * @property CalcType $calc_type
 * @property string|null $amount
 * @property string|null $percent
 * @property string|null $percent_of
 * @property string|null $formula
 * @property int $sort_order
 * @property-read SalaryComponent $component
 */
#[UseFactory(SalaryStructureComponentFactory::class)]
#[Fillable(['salary_structure_id', 'salary_component_id', 'calc_type', 'amount', 'percent', 'percent_of', 'formula', 'sort_order'])]
class SalaryStructureComponent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SalaryStructureComponentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['calc_type' => CalcType::class, 'amount' => 'decimal:2', 'percent' => 'decimal:2', 'sort_order' => 'integer'];
    }

    /**
     * @return BelongsTo<SalaryComponent, $this>
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id')->withTrashed();
    }

    /**
     * @return BelongsTo<SalaryStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id')->withTrashed();
    }
}
