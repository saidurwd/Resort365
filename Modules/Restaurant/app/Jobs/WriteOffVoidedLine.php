<?php

namespace Modules\Restaurant\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Restaurant\Actions\RecordVoidedLineWastage;
use Modules\Restaurant\Models\PosOrderLine;

/**
 * Writes off the food of a line voided after it was made (Step 5.7), in the background.
 */
class WriteOffVoidedLine implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $orderLineId) {}

    public function handle(RecordVoidedLineWastage $wastage): void
    {
        $line = PosOrderLine::query()->find($this->orderLineId);

        if ($line instanceof PosOrderLine) {
            $wastage->handle($line);
        }
    }
}
