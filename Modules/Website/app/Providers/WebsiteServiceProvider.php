<?php

namespace Modules\Website\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantHomePage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Modules\Reservation\Contracts\GuestEmailLinks;
use Modules\Website\Http\Middleware\ResolveSite;
use Modules\Website\Models\WebsiteBooking;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Policies\WebsiteSitePolicy;
use Modules\Website\Services\WebsiteHome;
use Nwidart\Modules\Support\ModuleServiceProvider;

class WebsiteServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Website';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'website';

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

        $this->app->bind(TenantHomePage::class, WebsiteHome::class);
    }

    public function boot(): void
    {
        parent::boot();

        Route::aliasMiddleware('website.site', ResolveSite::class);
        Relation::morphMap(['website_site' => WebsiteSite::class]);
        Gate::policy(WebsiteSite::class, WebsiteSitePolicy::class);

        // The public pages, per visitor's address (Step 8.2a).
        RateLimiter::for('website', fn (Request $request): Limit => Limit::perMinute(120)->by('website|'.(app(TenantContext::class)->id() ?? 0).'|'.$request->ip()));
        // Booking and cancelling (Step 8.2b): five tries per ten minutes per address.
        RateLimiter::for('website-checkout', fn (Request $request): Limit => Limit::perMinutes(10, 5)->by('website-checkout|'.(app(TenantContext::class)->id() ?? 0).'|'.$request->ip()));

        // The booking emails link to the guest's booking page (Step 8.2b).
        $this->app->make(GuestEmailLinks::class)->register(function (int $reservationId): ?array {
            $booking = WebsiteBooking::query()->with('site')->where('reservation_id', $reservationId)->first();

            return $booking instanceof WebsiteBooking && $booking->site instanceof WebsiteSite
                ? ['label' => __('Manage your booking'), 'url' => URL::signedRoute('website.booking.show', ['site' => $booking->site->slug, 'code' => $booking->reservation_code])]
                : null;
        });

        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $managers = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager];

        $permissions->register('Website', [
            new PermissionDefinition('website.content.view', 'See the website settings and preview an unpublished site', $managers),
            new PermissionDefinition('website.content.manage', 'Change the website: settings, texts, photos, rooms shown, publishing', $managers),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('website', 'Website', 'bi-globe2', order: 440);
        $menu->add(new MenuItem('website.site', 'Site settings', route: 'website.admin.site.edit', parent: 'website', order: 10,
            permission: 'website.content.view', module: 'website', active: 'website.admin.site.*'));
        $menu->add(new MenuItem('website.units', 'Rooms and cottages', route: 'website.admin.units.edit', parent: 'website', order: 20,
            permission: 'website.content.view', module: 'website', active: 'website.admin.units.*'));
    }
}
