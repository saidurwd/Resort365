<?php

namespace Modules\IAM\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingType;
use Modules\IAM\Actions\AuthenticateUser;
use Modules\IAM\Actions\Fortify\ResetUserPassword;
use Modules\IAM\Actions\Fortify\UpdateUserPassword;
use Modules\IAM\Actions\Fortify\UpdateUserProfileInformation;
use Modules\IAM\Console\SyncPermissionsCommand;
use Modules\IAM\Contracts\PosPins;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\Http\Middleware\EnsureTwoFactorEnabled;
use Modules\IAM\Http\Middleware\EnsureUserIsActive;
use Modules\IAM\Http\Middleware\SetUserLocale;
use Modules\IAM\Models\Role;
use Modules\IAM\Models\User;
use Modules\IAM\Policies\RolePolicy;
use Modules\IAM\Policies\UserPolicy;
use Modules\IAM\Services\PosPinsService;
use Modules\IAM\Services\UserDirectoryService;
use Modules\IAM\Support\TenantLoginRateLimiter;
use Nwidart\Modules\Support\ModuleServiceProvider;

class IAMServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'IAM';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'iam';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SyncPermissionsCommand::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function register(): void
    {
        parent::register();

        // Fortify serves tenant users only: its routes live on tenant subdomains.
        config(['fortify.domain' => '{tenant}.'.config('tenancy.central_domain')]);

        $this->app->bind(LoginRateLimiter::class, TenantLoginRateLimiter::class);
        $this->app->singleton(UserDirectory::class, UserDirectoryService::class);
        $this->app->singleton(PosPins::class, PosPinsService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->configureFortify();
        $this->configureRateLimiting();
        $this->configureMiddleware();

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->app->make(Settings::class)->define(new SettingDefinition(
            'iam.require_two_factor', 'Require two-factor authentication for owners and general managers', SettingType::Boolean, false,
            group: 'Security', help: 'They are asked to turn it on before they can continue.',
        ));
        $this->registerMenu($this->app->make(MenuRegistry::class));

        // Reset links carry the plain email; tokens are stored per tenant (User::getEmailForPasswordReset()).
        ResetPassword::createUrlUsing(fn (User $user, string $token): string => route('password.reset', ['token' => $token, 'email' => $user->email]));

        $this->shareLayoutData();
    }

    private function configureFortify(): void
    {
        Fortify::authenticateUsing(fn (Request $request): ?User => AuthenticateUser::make()->handle(
            (string) $request->input(Fortify::username()),
            (string) $request->input('password'),
        ));

        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::loginView(fn (): Factory|\Illuminate\Contracts\View\View => view('iam::auth.login'));
        Fortify::requestPasswordResetLinkView(fn (): Factory|\Illuminate\Contracts\View\View => view('iam::auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request): Factory|\Illuminate\Contracts\View\View => view('iam::auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn (): Factory|\Illuminate\Contracts\View\View => view('iam::auth.verify-email'));
        Fortify::twoFactorChallengeView(fn (): Factory|\Illuminate\Contracts\View\View => view('iam::auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn (): Factory|\Illuminate\Contracts\View\View => view('iam::auth.confirm-password'));
    }

    private function configureRateLimiting(): void
    {
        // Sign-in attempts are limited by Fortify itself (config fortify.limiters.login = null):
        // 5 failed attempts per tenant, email and IP (TenantLoginRateLimiter), with a message and a Lockout event.
        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)->by(
            app(TenantContext::class)->id().'|'.$request->session()->get('login.id')
        ));
    }

    /**
     * Adds IAM checks to the `tenant` group, in order: after the tenant and
     * tenant-membership checks, before authentication. Registered through the HTTP
     * kernel: it re-syncs its groups to the router, which would drop router-only changes.
     */
    private function configureMiddleware(): void
    {
        $kernel = $this->app->make(HttpKernel::class);
        $kernel->appendMiddlewareToGroup('tenant', EnsureUserIsActive::class);
        $kernel->appendMiddlewareToGroup('tenant', SetUserLocale::class);
        $kernel->appendMiddlewareToGroup('tenant', EnsureTwoFactorEnabled::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, EnsureUserIsActive::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, SetUserLocale::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, EnsureTwoFactorEnabled::class);
    }

    /**
     * Navbar user menu and the saved colour mode, for the shared layouts.
     */
    private function shareLayoutData(): void
    {
        View::composer(['layouts.partials.navbar', 'layouts.partials.head'], function (\Illuminate\View\View $view): void {
            $user = auth('web')->user();

            if (! $user instanceof User) {
                return;
            }

            $view->with('userMenu', [
                'name' => $user->name,
                'email' => $user->email,
                'items' => [
                    ['label' => __('Profile'), 'icon' => 'bi-person', 'url' => route('iam.profile.show')],
                ],
                'logoutUrl' => route('logout'),
            ]);
            $view->with('userTheme', $user->theme);
            $view->with('themeSaveUrl', route('iam.profile.preferences.update'));
        });
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $managers = [DefaultRole::GeneralManager];

        $permissions->register('Users & access', [
            new PermissionDefinition('iam.user.view', 'View users', $managers),
            new PermissionDefinition('iam.user.invite', 'Invite users', $managers),
            new PermissionDefinition('iam.user.update', 'Change users (roles, activate, deactivate)', $managers),
            new PermissionDefinition('iam.role.view', 'View roles', $managers),
            new PermissionDefinition('iam.role.create', 'Create roles'),
            new PermissionDefinition('iam.role.update', 'Change roles'),
            new PermissionDefinition('iam.role.delete', 'Delete roles'),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('iam.users', 'Users', 'bi-people', route: 'iam.users.index', parent: 'setup', order: 10,
            permission: 'iam.user.view', module: 'iam', active: 'iam.users.*'));
        $menu->add(new MenuItem('iam.roles', 'Roles & permissions', 'bi-shield-check', route: 'iam.roles.index', parent: 'setup', order: 20,
            permission: 'iam.role.view', module: 'iam', active: 'iam.roles.*'));
    }
}
