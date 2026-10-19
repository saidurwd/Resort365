<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Payroll\Database\Factories\SalaryComponentFactory;
use Modules\Payroll\Enums\ComponentFrequency;
use Modules\Payroll\Enums\ComponentKind;

/**
 * Something on a payslip (Step 6.4): an earning, a deduction or an employer cost. `BASIC` is what percentages are taken
 * of and `TAX` is worked out by the income tax rules; both are system components. A component paid "a few times a year"
 * (the festival bonuses) is not in the monthly figures but counts in the yearly income for tax.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $code
 * @property string $name
 * @property ComponentKind $kind
 * @property bool $is_taxable
 * @property ComponentFrequency $frequency
 * @property int $annual_times
 * @property bool $is_system
 * @property int $sort_order
 * @property bool $is_active
 */
#[UseFactory(SalaryComponentFactory::class)]
#[Fillable(['code', 'name', 'kind', 'is_taxable', 'frequency', 'annual_times', 'is_system', 'sort_order', 'is_active'])]
class SalaryComponent extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SalaryComponentFactory> */
    use HasFactory;

    use RecordsActivity;
    use SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ComponentKind::class, 'is_taxable' => 'boolean', 'frequency' => ComponentFrequency::class, 'annual_times' => 'integer', 'is_system' => 'boolean', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }
}
