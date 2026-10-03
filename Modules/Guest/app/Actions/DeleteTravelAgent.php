<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Models\TravelAgent;

/**
 * Deletes (soft) a travel agent; existing links keep showing its name.
 */
class DeleteTravelAgent extends Action
{
    public function handle(TravelAgent $travelAgent): void
    {
        $travelAgent->delete();
    }
}
