<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Str;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Models\KitchenStation;

/**
 * Gives a station's kitchen display a (new) device token (Step 3.5): only its hash is stored, the plain
 * token is returned once to be entered on the screen at /kds/register; a screen with the old token is
 * signed out. Stations that only print have no display.
 */
class RegisterStationDisplay extends Action
{
    /**
     * @return string the plain device token
     *
     * @throws RestaurantSetupInvalid
     */
    public function handle(KitchenStation $station): string
    {
        if (! $station->hasDisplay()) {
            throw new RestaurantSetupInvalid(__(':station only prints its tickets: choose a display output first.', ['station' => $station->name]));
        }

        $token = strtoupper(implode('-', str_split(Str::random(16), 4)));
        $station->forceFill(['display_token' => hash('sha256', $token)])->save();

        return $token;
    }
}
