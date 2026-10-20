<?php

namespace Modules\Payroll\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Modules\Payroll\Database\Factories\PayslipLineFactory;
use Modules\Payroll\Enums\ComponentKind;
use Modules\Payroll\Enums\LineSource;

/**
 * One line of a payslip (Step 6.5); locked with the payslip.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $payslip_id
 * @property string $code
 * @property string $name
 * @property ComponentKind $kind
 * @property bool $is_taxable
 * @property string $amount
 * @property LineSource $source
 * @property string|null $note
 * @property int $sort_order
 */
#[UseFactory(PayslipLineFactory::class)]
#[Fillable(['payslip_id', 'code', 'name', 'kind', 'is_taxable', 'amount', 'source', 'note', 'sort_order'])]
class PayslipLine extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<PayslipLineFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['kind' => ComponentKind::class, 'is_taxable' => 'boolean', 'amount' => 'decimal:2', 'source' => LineSource::class, 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::updating(fn (self $line) => self::guardPayslip($line->payslip_id));
        static::deleting(fn (self $line) => self::guardPayslip($line->payslip_id));
    }

    private static function guardPayslip(int $payslipId): void
    {
        $runId = DB::table('payslips')->where('id', $payslipId)->value('payroll_run_id');

        if (is_numeric($runId)) {
            Payslip::assertRunOpen((int) $runId);
        }
    }

    /**
     * @return BelongsTo<Payslip, $this>
     */
    public function payslip(): BelongsTo
    {
        return $this->belongsTo(Payslip::class);
    }
}
