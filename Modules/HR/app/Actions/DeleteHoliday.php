<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Models\Holiday;

/**
 * Removes a holiday (Step 6.2); attendance already recorded keeps its figures.
 */
class DeleteHoliday extends Action
{
    public function handle(Holiday $holiday): void
    {
        $holiday->delete();
    }
}
