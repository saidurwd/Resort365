<?php

namespace Modules\IAM\Http\Middleware;

use App\Support\Authorization\DefaultRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Contracts\Settings;
use Modules\IAM\Models\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * When the tenant requires it (setting iam.require_two_factor), Tenant Owners and General
 * Managers must turn on two-factor authentication before using anything but their profile
 * (ARCHITECTURE §9.1).
 */
class EnsureTwoFactorEnabled
{
    /**
     * Roles the requirement applies to.
     */
    public const array ROLES = [DefaultRole::TenantOwner, DefaultRole::GeneralManager];

    /**
     * Routes that stay reachable, so the user can set up 2FA or sign out.
     */
    private const array EXEMPT = [
        'iam.profile.*', 'two-factor.*', 'password.confirm', 'password.confirm.store', 'password.confirmation',
        'logout', 'verification.*', 'user-profile-information.update', 'user-password.update',
    ];

    public function __construct(private readonly Settings $settings) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User
            || $user->hasTwoFactorEnabled()
            || $request->routeIs(...self::EXEMPT)
            || ! $this->settings->get('iam.require_two_factor')
            || ! $user->hasAnyRole(array_map(fn (DefaultRole $role): string => $role->value, self::ROLES))) {
            return $next($request);
        }

        return redirect()->route('iam.profile.show')
            ->with('warning', __('Your company requires two-factor authentication for your role. Please turn it on to continue.'));
    }
}
