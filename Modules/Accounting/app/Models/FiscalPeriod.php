<?php

namespace Modules\Accounting\Models;

use App\Support\Audit\RecordsActivity;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\Accounting\Database\Factories\FiscalPeriodFactory;
use Modules\Accounting\Enums\PeriodStatus;

/**
 * A month of a fiscal year: open to posting, closed or locked (ARCHITECTURE §5.14). Posting dates are
 * matched to the period that contains them.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $fiscal_year_id
 * @property string $name
 * @property int $number
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property PeriodStatus $status
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 */
#[UseFactory(FiscalPeriodFactory::class)]
#[Fillable(['fiscal_year_id', 'name', 'number', 'starts_on', 'ends_on', 'status', 'closed_at', 'closed_by'])]
class FiscalPeriod extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<FiscalPeriodFactory> */
    use HasFactory;

    use RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'status' => PeriodStatus::class, 'closed_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<FiscalYear, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(FiscalYear::class, 'fiscal_year_id');
    }
}
