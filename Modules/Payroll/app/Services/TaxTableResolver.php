<?php

namespace Modules\Payroll\Services;

use Modules\Payroll\DTOs\TaxTableData;
use Modules\Payroll\Models\TaxSlab;
use Modules\Payroll\Models\TaxTable;

/**
 * Finds the income tax table in force on a date (Step 6.4): the active table with the latest start on or before it.
 */
class TaxTableResolver
{
    public function forDate(string $date): ?TaxTable
    {
        return TaxTable::query()->with('slabs')->where('is_active', true)->whereDate('effective_from', '<=', $date)->orderByDesc('effective_from')->orderByDesc('id')->first();
    }

    public function data(TaxTable $table): TaxTableData
    {
        $table->loadMissing('slabs');

        return new TaxTableData(
            collect($table->thresholds)->mapWithKeys(fn (array $row): array => [$row['code'] => (string) $row['amount']])->all(),
            collect($table->minimum_tax)->mapWithKeys(fn (array $row): array => [$row['code'] => (string) $row['amount']])->all(),
            (string) $table->exemption_fraction, (string) $table->exemption_cap,
            $table->slabs->map(fn (TaxSlab $slab): array => ['width' => $slab->width !== null ? (string) $slab->width : null, 'rate' => (string) $slab->rate])->values()->all(),
        );
    }
}
