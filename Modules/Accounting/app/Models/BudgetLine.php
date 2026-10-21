<?php

namespace Modules\Accounting\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Accounting\Database\Factories\BudgetLineFactory;

/**
 * One income or expense account (and optionally one department) of a budget, with an amount for each of the twelve months
 * (Step 7.2). Amounts are positive: income expected, or expense allowed.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $budget_id
 * @property int $account_id
 * @property int|null $department_id
 * @property string $month_1
 * @property string $month_2
 * @property string $month_3
 * @property string $month_4
 * @property string $month_5
 * @property string $month_6
 * @property string $month_7
 * @property string $month_8
 * @property string $month_9
 * @property string $month_10
 * @property string $month_11
 * @property string $month_12
 * @property-read Account $account
 */
#[UseFactory(BudgetLineFactory::class)]
#[Fillable(['budget_id', 'account_id', 'department_id', 'month_1', 'month_2', 'month_3', 'month_4', 'month_5', 'month_6', 'month_7', 'month_8', 'month_9', 'month_10', 'month_11', 'month_12'])]
class BudgetLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<BudgetLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['month_1' => 'decimal:2', 'month_2' => 'decimal:2', 'month_3' => 'decimal:2', 'month_4' => 'decimal:2', 'month_5' => 'decimal:2', 'month_6' => 'decimal:2', 'month_7' => 'decimal:2', 'month_8' => 'decimal:2', 'month_9' => 'decimal:2', 'month_10' => 'decimal:2', 'month_11' => 'decimal:2', 'month_12' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * The twelve months, month 1 first.
     *
     * @return list<string>
     */
    public function months(): array
    {
        return array_map(fn (int $month): string => (string) $this->getAttribute('month_'.$month), range(1, 12));
    }
}
