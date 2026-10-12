<?php

namespace Modules\Restaurant\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Restaurant\Actions\RecordBillConsumption;
use Modules\Restaurant\Models\PosBill;

/**
 * Takes a settled bill's ingredients out of stock in the background, so the POS never waits for Inventory (Step 5.7).
 */
class ConsumeBillIngredients implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $billId) {}

    public function handle(RecordBillConsumption $consume): void
    {
        $bill = PosBill::query()->find($this->billId);

        if ($bill instanceof PosBill) {
            $consume->handle($bill);
        }
    }
}
