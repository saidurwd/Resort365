<?php

namespace Modules\IAM\Providers;

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
use Modules\IAM\Actions\AuthenticateUser;
use Modules\IAM\Actions\Fortify\ResetUserPassword;
use Modules\IAM\Actions\Fortify\UpdateUserPassword;
use Modules\IAM\Actions\Fortify\UpdateUserProfileInformation;
use Modules\IAM\Http\Middleware\EnsureUserIsActive;
use Modules\IAM\Http\Middleware\SetUserLocale;
use Modules\IAM\Models\User;
use Modules\IAM\Policies\UserPolicy;
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
    }

    public function boot(): void
    {
        parent::boot();

        $this->configureFortify();
        $this->configureRateLimiting();
        $this->configureMiddleware();

        Gate::policy(User::class, UserPolicy::class);

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
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, EnsureUserIsActive::class);
        $kernel->addToMiddlewarePriorityBefore(AuthenticatesRequests::class, SetUserLocale::class);
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
}
