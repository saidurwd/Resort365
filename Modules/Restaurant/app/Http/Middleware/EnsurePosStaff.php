<?php

namespace Modules\Restaurant\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\Settings;
use Modules\Restaurant\Services\PosContext;
use Modules\Restaurant\Services\PosStaff;
use Symfony\Component\HttpFoundation\Response;

/**
 * POS actions need a signed-in person who may work on this terminal (PosStaff). After
 * restaurant.pos_auto_lock_minutes without a request the terminal locks: the person is signed out
 * and the next one picks their name and enters their PIN.
 */
class EnsurePosStaff
{
    public const string ACTIVITY = 'pos_last_activity';

    public function __construct(
        private readonly PosContext $context,
        private readonly PosStaff $staff,
        private readonly Settings $settings,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $terminal = $this->context->terminal();
        $userId = Auth::guard('web')->id();
        $idle = (int) $this->settings->get('restaurant.pos_auto_lock_minutes', $terminal->property_id);
        $last = $request->session()->get(self::ACTIVITY);
        $expired = $idle > 0 && is_int($last) && $last < now()->subMinutes($idle)->getTimestamp();

        if ($userId === null || $expired || ! $this->staff->canSignIn((int) $userId, $terminal)) {
            if ($userId !== null) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return $request->expectsJson()
                ? response()->json(['message' => __('The terminal is locked; sign in with your PIN.')], 401)
                : redirect()->route('pos.home');
        }

        $request->session()->put(self::ACTIVITY, now()->getTimestamp());

        return $next($request);
    }
}
