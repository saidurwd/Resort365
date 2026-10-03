<?php

namespace Modules\Core\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Support\Facades\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\ExchangeRates;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Contracts\ReferenceData;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Core\Services\AuditTrailService;
use Modules\Core\Services\DocumentNumberService;
use Modules\Core\Services\ExchangeRateService;
use Modules\Core\Services\NotificationTemplateService;
use Modules\Core\Services\ReferenceDataService;
use Modules\Core\Services\SettingsService;
use Nwidart\Modules\Support\ModuleServiceProvider;

class CoreServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Core';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'core';

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

        // Registries (filled at boot) and stateless services behind the contracts other modules use.
        $this->app->singleton(Settings::class, SettingsService::class);
        $this->app->singleton(DocumentNumbers::class, DocumentNumberService::class);
        $this->app->singleton(NotificationTemplates::class, NotificationTemplateService::class);
        $this->app->singleton(AuditTrail::class, AuditTrailService::class);
        $this->app->singleton(ExchangeRates::class, ExchangeRateService::class);
        $this->app->singleton(ReferenceData::class, ReferenceDataService::class);
    }

    public function boot(): void
    {
        parent::boot();

        $this->registerSettings($this->app->make(Settings::class));
        $this->registerDocumentTypes($this->app->make(DocumentNumbers::class));
        $this->app->make(NotificationTemplates::class)->register(new NotificationTemplateDefinition(
            'core.welcome', 'database', 'Welcome to {tenant}', 'Hi {name}, your account is ready. Explore the menu on the left to get started.', ['name', 'tenant'],
        ));
        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
        $this->shareNotificationBell();
    }

    private function registerSettings(Settings $settings): void
    {
        $settings->define(new SettingDefinition('core.timezone', 'Timezone', SettingType::Timezone, 'Asia/Dhaka', group: 'General',
            help: 'Times are stored in UTC and shown in this timezone.'));
        $settings->define(new SettingDefinition('core.currency', 'Base currency', SettingType::Currency, 'BDT', group: 'General',
            help: 'The currency the company reports in (ISO 4217).'));
        $settings->define(new SettingDefinition('core.date_format', 'Date format', SettingType::Select, 'd M Y', group: 'General',
            options: ['d M Y' => '27 Sep 2026', 'd/m/Y' => '27/09/2026', 'Y-m-d' => '2026-09-27', 'm/d/Y' => '09/27/2026']));

        // Check-in/out times are property columns (Property module), not settings.
        $settings->define(new SettingDefinition('core.night_audit_time', 'Night audit time', SettingType::Time, '02:00', SettingScope::Property, 'Operations',
            help: 'When the automatic night audit runs, in the property\'s timezone.'));
        $settings->define(new SettingDefinition('core.default_deposit_percent', 'Default deposit %', SettingType::Integer, 30, SettingScope::Property, 'Operations',
            help: 'Advance deposit asked for new bookings (30–50%).', rules: ['min:0', 'max:100']));
    }

    /**
     * The standard numbered documents (ARCHITECTURE §5.1); the modules that own them use these keys.
     */
    private function registerDocumentTypes(DocumentNumbers $numbers): void
    {
        foreach ([
            'reservation' => ['Reservation', 'RSV'],
            'invoice' => ['Invoice', 'INV'],
            'purchase_order' => ['Purchase order', 'PO'],
            'goods_receipt' => ['Goods receipt', 'GRN'],
            'journal' => ['Journal voucher', 'JV'],
            'payment' => ['Payment receipt', 'PAY'],
        ] as $key => [$label, $prefix]) {
            $numbers->register(new DocumentType($key, $label, $prefix));
        }
    }

    private function registerPermissions(PermissionRegistry $permissions): void
    {
        $managers = [DefaultRole::GeneralManager];

        $permissions->register('Settings & records', [
            new PermissionDefinition('core.setting.view', 'View settings', $managers),
            new PermissionDefinition('core.setting.update', 'Change settings', $managers),
            new PermissionDefinition('core.sequence.view', 'View document numbering', $managers),
            new PermissionDefinition('core.sequence.update', 'Change document numbering', $managers),
            new PermissionDefinition('core.audit.view', 'View the audit log', $managers),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('core.settings', 'Settings', route: 'core.settings.index', parent: 'setup', order: 30,
            permission: 'core.setting.view', module: 'core', active: 'core.settings.*'));
        $menu->add(new MenuItem('core.sequences', 'Document numbering', route: 'core.sequences.index', parent: 'setup', order: 40,
            permission: 'core.sequence.view', module: 'core', active: 'core.sequences.*'));
        $menu->add(new MenuItem('core.audit', 'Audit log', route: 'core.audit.index', parent: 'setup', order: 50,
            permission: 'core.audit.view', module: 'core', active: 'core.audit.*'));
    }

    /**
     * Unread count and latest notifications for the navbar bell (signed-in tenant users).
     */
    private function shareNotificationBell(): void
    {
        View::composer('layouts.partials.navbar', function (\Illuminate\View\View $view): void {
            $user = auth('web')->user();

            if ($user === null) {
                return;
            }

            $view->with('notificationBell', [
                'unread' => $user->unreadNotifications()->count(),
                'latest' => $user->notifications()->latest()->limit(5)->get(),
                'indexUrl' => route('core.notifications.index'),
                'readAllUrl' => route('core.notifications.read-all'),
            ]);
        });
    }
}
