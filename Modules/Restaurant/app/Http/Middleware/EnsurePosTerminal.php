<?php

namespace Modules\Restaurant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Services\PosContext;
use Modules\Restaurant\Services\PosDevice;
use Symfony\Component\HttpFoundation\Response;

/**
 * POS routes only work on a registered terminal (device cookie): otherwise the device is sent to
 * the registration screen (JSON: 401). Sets PosContext and notes when the terminal was last seen.
 */
class EnsurePosTerminal
{
    public function __construct(
        private readonly PosDevice $device,
        private readonly PosContext $context,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $terminal = $this->device->fromRequest($request);

        if (! $terminal instanceof PosTerminal) {
            return $request->expectsJson()
                ? response()->json(['message' => __('This device is not a registered POS terminal.')], 401)
                : redirect()->route('pos.register')->withCookie($this->device->forget());
        }

        $this->context->set($terminal);

        if ($terminal->last_seen_at === null || $terminal->last_seen_at->lt(now()->subMinute())) {
            $terminal->forceFill(['last_seen_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
