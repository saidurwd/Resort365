<?php

namespace Modules\Platform\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Platform\Actions\AuthenticatePlatformAdmin;
use Modules\Platform\Actions\ConfirmPlatformTwoFactor;
use Modules\Platform\Actions\StartPlatformTwoFactor;
use Modules\Platform\Actions\VerifyPlatformTwoFactor;
use Modules\Platform\Http\Middleware\EnsurePlatformTwoFactor;
use Modules\Platform\Models\PlatformAdmin;

/**
 * Two-factor sign-in for platform admins (Phase 7 review): the second step after the password, and setting it up from the console
 * (Security). In production an admin without it is sent to set it up before anything else (EnsurePlatformTwoFactor).
 */
class PlatformTwoFactorController extends Controller
{
    public function challenge(Request $request): View|RedirectResponse
    {
        return $this->pending($request) instanceof PlatformAdmin ? view('platform::auth.two-factor-challenge') : redirect()->route('platform.login');
    }

    public function verify(Request $request, VerifyPlatformTwoFactor $verify, AuthenticatePlatformAdmin $authenticate): RedirectResponse
    {
        $request->validate(['code' => ['nullable', 'string', 'max:10'], 'recovery_code' => ['nullable', 'string', 'max:40']]);
        $admin = $this->pending($request);

        if (! $admin instanceof PlatformAdmin) {
            return redirect()->route('platform.login')->with('error', __('Sign in again.'));
        }

        if (! $verify->handle($admin, $request->input('code'), $request->input('recovery_code'))) {
            return back()->withErrors(['code' => __('That code is not right.')]);
        }

        $remember = (bool) ($request->session()->get(PlatformLoginController::PENDING)['remember'] ?? false);
        $request->session()->forget(PlatformLoginController::PENDING);
        $authenticate->signIn($admin, $remember, (string) $request->ip());
        $request->session()->regenerate();

        return redirect()->intended(route('platform.console'));
    }

    public function show(Request $request, StartPlatformTwoFactor $start): View
    {
        /** @var PlatformAdmin $admin */
        $admin = $request->user('platform');

        if (! $admin->hasEnabledTwoFactorAuthentication() && $admin->two_factor_secret === null) {
            $start->handle($admin);
        }

        return view('platform::security.two-factor', ['admin' => $admin->refresh(), 'required' => EnsurePlatformTwoFactor::required()]);
    }

    public function confirm(Request $request, ConfirmPlatformTwoFactor $confirm): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        /** @var PlatformAdmin $admin */
        $admin = $request->user('platform');
        $confirm->handle($admin, (string) $request->input('code'));

        return redirect()->route('platform.two-factor.show')->with('success', __('Two-factor sign-in is on. Keep the recovery codes below somewhere safe.'))->with('show_codes', true);
    }

    /** The admin waiting for the second step, while the five minutes last. */
    private function pending(Request $request): ?PlatformAdmin
    {
        $pending = $request->session()->get(PlatformLoginController::PENDING);

        if (! is_array($pending) || ($pending['until'] ?? 0) < now()->timestamp) {
            return null;
        }

        return PlatformAdmin::query()->find($pending['id']);
    }
}
