<?php

namespace Modules\Payroll\Actions;

use App\Support\Actions\Action;
use Modules\Payroll\Models\TaxTable;

/**
 * Removes an income tax table and its slabs (Step 6.4). Payslips already made keep the tax they worked out (Step 6.5).
 */
class DeleteTaxTable extends Action
{
    public function handle(TaxTable $table): void
    {
        $this->transaction(function () use ($table): void {
            $table->slabs()->delete();
            $table->delete();
        });
    }
}
