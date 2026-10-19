<?php

namespace Modules\Payroll\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Payroll\Database\Factories\EmployeeSalaryFactory;

/**
 * An employee's pay from a date (Step 6.4): the monthly basic, the structure, amounts set for this employee and the tax
 * category and area. A new record closes the one before it; a record that has started is not edited, a new one replaces it.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $employee_id
 * @property int $salary_structure_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property string $basic
 * @property array<string, string>|null $amounts
 * @property string $tax_category
 * @property string $tax_area
 * @property string|null $notes
 * @property int|null $created_by
 * @property-read SalaryStructure $structure
 */
#[UseFactory(EmployeeSalaryFactory::class)]
#[Fillable(['employee_id', 'salary_structure_id', 'effective_from', 'effective_to', 'basic', 'amounts', 'tax_category', 'tax_area', 'notes', 'created_by'])]
class EmployeeSalary extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<EmployeeSalaryFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'basic' => 'decimal:2', 'amounts' => 'array'];
    }

    /**
     * @return BelongsTo<SalaryStructure, $this>
     */
    public function structure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id')->withTrashed();
    }
}
