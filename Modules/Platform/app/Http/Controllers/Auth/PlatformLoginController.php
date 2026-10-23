<?php

namespace Modules\Platform\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Modules\Platform\Actions\AuthenticatePlatformAdmin;
use Modules\Platform\Http\Requests\PlatformLoginRequest;

class PlatformLoginController extends Controller
{
    /** The session key of an admin who passed the password and owes a two-factor code: id, remember, until. */
    public const string PENDING = 'platform.two_factor.pending';

    public function create(): View
    {
        return view('platform::auth.login');
    }

    public function store(PlatformLoginRequest $request, AuthenticatePlatformAdmin $authenticate): RedirectResponse
    {
        $admin = $authenticate->handle((string) $request->string('email'), (string) $request->string('password'), $request->boolean('remember'), (string) $request->ip());

        $request->session()->regenerate();

        if (! Auth::guard('platform')->check()) {
            $request->session()->put(self::PENDING, ['id' => $admin->id, 'remember' => $request->boolean('remember'), 'until' => now()->addMinutes(5)->timestamp]);

            return redirect()->route('platform.two-factor.challenge');
        }

        return redirect()->intended(route('platform.console'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('platform')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
