<?php

namespace Modules\Restaurant\Http\Controllers\Pos;

use App\Http\Middleware\SetCurrentProperty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Restaurant\Actions\CheckPosPin;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Http\Middleware\EnsurePosStaff;
use Modules\Restaurant\Http\Requests\PosSignInRequest;
use Modules\Restaurant\Http\Requests\RegisterDeviceRequest;
use Modules\Restaurant\Models\PosTerminal;
use Modules\Restaurant\Services\PosContext;
use Modules\Restaurant\Services\PosDevice;
use Modules\Restaurant\Services\PosStaff;

/**
 * The POS front door (ARCHITECTURE §10.1): registering a device with its terminal token, the lock
 * screen (staff tiles and a PIN pad), signing in by PIN (the fast user switch) and locking.
 */
class PosController extends Controller
{
    public function register(Request $request, PosDevice $device): View|RedirectResponse
    {
        return $device->fromRequest($request) instanceof PosTerminal ? to_route('pos.home') : view('restaurant::pos.register');
    }

    public function storeDevice(RegisterDeviceRequest $request, PosDevice $device): RedirectResponse
    {
        $terminal = $device->terminalForToken((string) $request->validated('token'));

        if (! $terminal instanceof PosTerminal) {
            return to_route('pos.register')->withErrors(['token' => __('No active terminal has this token.')]);
        }

        return to_route('pos.home')->withCookie($device->cookieFor($terminal))
            ->with('success', __('This device is now :terminal at :outlet.', ['terminal' => $terminal->name, 'outlet' => $terminal->outlet->name]));
    }

    public function home(Request $request, PosContext $context, PosStaff $staff): View|RedirectResponse
    {
        $terminal = $context->terminal();
        $userId = Auth::guard('web')->id();

        if ($userId !== null && $request->session()->has(EnsurePosStaff::ACTIVITY) && $staff->canSignIn((int) $userId, $terminal)) {
            return to_route('pos.main');
        }

        return view('restaurant::pos.lock', ['terminal' => $terminal, 'staff' => $staff->forTerminal($terminal)]);
    }

    public function signIn(PosSignInRequest $request, PosContext $context, CheckPosPin $check): RedirectResponse
    {
        $terminal = $context->terminal();
        $userId = (int) $request->validated('user_id');

        try {
            $check->handle($terminal, $userId, (string) $request->validated('pin'));
        } catch (PosNotAllowed $exception) {
            return to_route('pos.home')->withErrors(['pin' => $exception->getMessage()])->withInput($request->only('user_id'));
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }

        Auth::guard('web')->loginUsingId($userId);
        $request->session()->regenerate();
        $request->session()->put(SetCurrentProperty::SESSION_KEY, $terminal->property_id);
        $request->session()->put(EnsurePosStaff::ACTIVITY, now()->getTimestamp());

        return to_route('pos.main');
    }

    public function lock(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('pos.home');
    }
}
