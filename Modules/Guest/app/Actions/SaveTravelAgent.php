<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Models\TravelAgent;

/**
 * Creates or updates a travel agent (validated by SaveTravelAgentRequest).
 */
class SaveTravelAgent extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?TravelAgent $travelAgent, array $data): TravelAgent
    {
        $travelAgent ??= new TravelAgent;
        $travelAgent->fill($data)->save();

        return $travelAgent;
    }
}
