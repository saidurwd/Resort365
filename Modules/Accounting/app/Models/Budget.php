<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\BudgetFactory;

/**
 * A year's budget for a property, or for the whole company (no property): income and expense by account, department and month
 * (Step 7.2).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $fiscal_year_id
 * @property int|null $property_id
 * @property string $name
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read FiscalYear $fiscalYear
 */
#[UseFactory(BudgetFactory::class)]
#[Fillable(['fiscal_year_id', 'property_id', 'name', 'notes', 'created_by'])]
class Budget extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BudgetFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function fiscalYear(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class);
    }

    /**
     * @return HasMany<BudgetLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(BudgetLine::class)->orderBy('account_id')->orderBy('department_id');
    }
}
