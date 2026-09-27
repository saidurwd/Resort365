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
    public function create(): View
    {
        return view('platform::auth.login');
    }

    public function store(PlatformLoginRequest $request, AuthenticatePlatformAdmin $authenticate): RedirectResponse
    {
        $authenticate->handle(
            (string) $request->string('email'),
            (string) $request->string('password'),
            $request->boolean('remember'),
            (string) $request->ip(),
        );

        $request->session()->regenerate();

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
