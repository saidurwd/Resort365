<?php

namespace Modules\Restaurant\Http\Middleware;

use App\Support\Tenancy\DisplayTimezone;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Services\KdsContext;
use Modules\Restaurant\Services\KdsDevice;
use Modules\Restaurant\Services\OutletAccess;
use Symfony\Component\HttpFoundation\Response;

/**
 * The kitchen display's sign-in (ARCHITECTURE §10.1): a registered station display (cookie), or a person
 * signed in to the app with restaurant.kds.use who chose a station (?station=, remembered in the
 * session) of an outlet they work in. Otherwise: registration (devices) or the station picker (people).
 */
class EnsureKdsStation
{
    public const string SESSION = 'kds_station';

    public function __construct(
        private readonly KdsDevice $device,
        private readonly KdsContext $context,
        private readonly OutletAccess $outlets,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $station = $this->device->fromRequest($request);

        if ($station instanceof KitchenStation) {
            $this->context->set($station, null);
            app(DisplayTimezone::class)->use($station->property_id);

            return $next($request);
        }

        $user = Auth::guard('web')->user();

        if ($user === null || ! $user->can('restaurant.kds.use')) {
            return $request->expectsJson()
                ? response()->json(['message' => __('This screen is not a registered kitchen display.')], 401)
                : redirect()->route('kds.register');
        }

        $chosen = $request->integer('station') ?: (int) $request->session()->get(self::SESSION);
        $station = $chosen > 0 ? KitchenStation::query()->with('outlet')->find($chosen) : null;

        if (! $station instanceof KitchenStation || ! $station->hasDisplay() || ! $this->outlets->canUse((int) $user->getAuthIdentifier(), $station->outlet_id)) {
            $request->session()->forget(self::SESSION);

            return $request->expectsJson()
                ? response()->json(['message' => __('Choose a station.')], 403)
                : redirect()->route('kds.stations');
        }

        $request->session()->put(self::SESSION, $station->id);
        $this->context->set($station, (int) $user->getAuthIdentifier());
        app(DisplayTimezone::class)->use($station->property_id);

        return $next($request);
    }
}
