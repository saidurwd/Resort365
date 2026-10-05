<?php

namespace Modules\Restaurant\Services;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Modules\Restaurant\Models\KitchenStation;
use Symfony\Component\HttpFoundation\Cookie as CookieValue;

/**
 * Which station's kitchen display a browser is (ARCHITECTURE §10.1): entering the station's display
 * token once stores "station id | token hash" in a long-lived encrypted cookie. The screen stays signed
 * in (no idle timeout) while the token is unchanged and the station still shows tickets on a display.
 */
class KdsDevice
{
    public const string COOKIE = 'kds_device';

    private const int DAYS = 400;

    public function stationForToken(string $token): ?KitchenStation
    {
        $hash = hash('sha256', strtoupper(trim($token)));
        $station = app(PropertyContext::class)->unrestricted(fn (): ?KitchenStation => KitchenStation::query()->with('outlet')->where('display_token', $hash)->first());

        return $station instanceof KitchenStation && $station->hasDisplay() && $station->outlet->is_active ? $station : null;
    }

    public function cookieFor(KitchenStation $station): CookieValue
    {
        return Cookie::make(self::COOKIE, $station->id.'|'.$station->display_token, self::DAYS * 24 * 60, httpOnly: true, sameSite: 'lax');
    }

    public function forget(): CookieValue
    {
        return Cookie::forget(self::COOKIE);
    }

    public function fromRequest(Request $request): ?KitchenStation
    {
        $value = $request->cookie(self::COOKIE);

        if (! is_string($value) || ! str_contains($value, '|')) {
            return null;
        }

        [$id, $hash] = explode('|', $value, 2);
        $station = app(PropertyContext::class)->unrestricted(fn (): ?KitchenStation => KitchenStation::query()->with('outlet')->find((int) $id));

        return $station instanceof KitchenStation && $station->display_token !== null && hash_equals($station->display_token, $hash)
            && $station->hasDisplay() && $station->outlet->is_active ? $station : null;
    }
}
