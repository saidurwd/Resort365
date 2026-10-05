<?php

namespace Modules\Restaurant\Services;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Modules\Restaurant\Models\PosTerminal;
use Symfony\Component\HttpFoundation\Cookie as CookieValue;

/**
 * Which registered POS terminal a browser is (ARCHITECTURE §5.10.1): entering a terminal's device
 * token once stores "terminal id | token hash" in a long-lived encrypted cookie; the device stays
 * registered while the terminal is active and its token unchanged (a new token signs it out).
 */
class PosDevice
{
    public const string COOKIE = 'pos_device';

    private const int DAYS = 400;

    /**
     * The terminal of a device token, if it is active.
     */
    public function terminalForToken(string $token): ?PosTerminal
    {
        $hash = hash('sha256', strtoupper(trim($token)));

        return app(PropertyContext::class)->unrestricted(fn (): ?PosTerminal => PosTerminal::query()->where('device_token', $hash)->where('is_active', true)->first());
    }

    public function cookieFor(PosTerminal $terminal): CookieValue
    {
        return Cookie::make(self::COOKIE, $terminal->id.'|'.$terminal->device_token, self::DAYS * 24 * 60, httpOnly: true, sameSite: 'lax');
    }

    public function forget(): CookieValue
    {
        return Cookie::forget(self::COOKIE);
    }

    /**
     * The registered terminal this request comes from, if any.
     */
    public function fromRequest(Request $request): ?PosTerminal
    {
        $value = $request->cookie(self::COOKIE);

        if (! is_string($value) || ! str_contains($value, '|')) {
            return null;
        }

        [$id, $hash] = explode('|', $value, 2);
        $terminal = app(PropertyContext::class)->unrestricted(fn (): ?PosTerminal => PosTerminal::query()->with('outlet')->find((int) $id));

        return $terminal instanceof PosTerminal && $terminal->is_active && $terminal->outlet->is_active && hash_equals($terminal->device_token, $hash) ? $terminal : null;
    }
}
