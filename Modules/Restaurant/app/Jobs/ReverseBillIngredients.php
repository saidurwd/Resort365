<?php

namespace Modules\Restaurant\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Restaurant\Actions\ReverseBillConsumption;
use Modules\Restaurant\Models\PosBill;

/**
 * Puts a voided bill's ingredients back in stock in the background (Step 5.7).
 */
class ReverseBillIngredients implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $billId) {}

    public function handle(ReverseBillConsumption $reverse): void
    {
        $bill = PosBill::query()->find($this->billId);

        if ($bill instanceof PosBill) {
            $reverse->handle($bill);
        }
    }
}
