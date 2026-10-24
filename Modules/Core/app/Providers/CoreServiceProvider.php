<?php

namespace Modules\Core\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Modules\Core\Console\PurgeDataExportsCommand;
use Modules\Core\Console\SeedApprovalsCommand;
use Modules\Core\Contracts\Announcements;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\ExchangeRates;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Contracts\Onboarding;
use Modules\Core\Contracts\PlanLimits;
use Modules\Core\Contracts\PublicPhotos;
use Modules\Core\Contracts\ReferenceData;
use Modules\Core\Contracts\Settings;
use Modules\Core\Contracts\SubscriptionAccount;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\Contracts\TenantDataExports;
use Modules\Core\Contracts\UsageMeters;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\DTOs\OnboardingStep;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Core\Models\ApprovalRequest;
use Modules\Core\Models\TaxCategory;
use Modules\Core\Policies\ApprovalRequestPolicy;
use Modules\Core\Services\ApprovalEngineService;
use Modules\Core\Services\ApprovalRegistryService;
use Modules\Core\Services\AuditTrailService;
use Modules\Core\Services\DocumentNumberService;
use Modules\Core\Services\ExchangeRateService;
use Modules\Core\Services\NoAnnouncements;
use Modules\Core\Services\NoSubscriptionAccount;
use Modules\Core\Services\NotificationTemplateService;
use Modules\Core\Services\OnboardingService;
use Modules\Core\Services\PublicPhotoLinks;
use Modules\Core\Services\ReferenceDataService;
use Modules\Core\Services\SettingsService;
use Modules\Core\Services\TaxEngineService;
use Modules\Core\Services\TenantDataExportService;
use Modules\Core\Services\UnlimitedPlanLimits;
use Modules\Core\Services\UsageMeterRegistry;
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

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SeedApprovalsCommand::class,
        PurgeDataExportsCommand::class,
    ];

    public function register(): void
    {
        parent::register();

        // Registries (filled at boot) and stateless services behind the contracts other modules use.
        $this->app->singleton(Settings::class, SettingsService::class);
        $this->app->singleton(Onboarding::class, OnboardingService::class);
        $this->app->singleton(DocumentNumbers::class, DocumentNumberService::class);
        $this->app->singleton(NotificationTemplates::class, NotificationTemplateService::class);
        $this->app->singleton(AuditTrail::class, AuditTrailService::class);
        $this->app->singleton(PublicPhotos::class, PublicPhotoLinks::class);
        // Plans: no plan means no limits and no announcements; Platform replaces these (Step 7.3).
        $this->app->singletonIf(PlanLimits::class, UnlimitedPlanLimits::class);
        $this->app->singleton(UsageMeters::class, UsageMeterRegistry::class);
        $this->app->singletonIf(Announcements::class, NoAnnouncements::class);
        // Subscriptions (Step 7.4): Platform binds the real account; a company's data exports are Core's.
        $this->app->bindIf(SubscriptionAccount::class, NoSubscriptionAccount::class);
        $this->app->bind(TenantDataExports::class, TenantDataExportService::class);
        $this->app->singleton(ExchangeRates::class, ExchangeRateService::class);
        $this->app->singleton(ReferenceData::class, ReferenceDataService::class);
        $this->app->singleton(TaxEngine::class, TaxEngineService::class);
        $this->app->singleton(ApprovalRegistry::class, ApprovalRegistryService::class);
        $this->app->bind(ApprovalEngine::class, ApprovalEngineService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Files kept for the tenant, in gigabytes (Step 7.3).
        $this->app->make(UsageMeters::class)->register('storage', 'Storage', 'GB', fn (): float => round((float) DB::table('media')->where('tenant_id', $this->app->make(TenantContext::class)->tenantOrFail(self::class)->id)->sum('size') / 1024 ** 3, 4));

        $this->registerSettings($this->app->make(Settings::class));
        $this->registerDocumentTypes($this->app->make(DocumentNumbers::class));
        $this->app->make(NotificationTemplates::class)->register(new NotificationTemplateDefinition(
            'core.welcome', 'database', 'Welcome to {tenant}', 'Hi {name}, your account is ready. Explore the menu on the left to get started.', ['name', 'tenant'],
            'Welcome message', 'In-app, when a user account is created.',
        ));
        foreach ([
            ['requested', 'Approval needed: {reference}', '{person} asks for your approval of {reference} ({amount}).', ['reference', 'amount', 'person', 'comment'], 'Approval requested', 'In-app, to the people who may decide a step.'],
            ['approved', '{reference} was approved', '{person} approved {reference} ({amount}). {comment}', ['reference', 'amount', 'person', 'comment'], 'Approval granted', 'In-app, to the person who asked.'],
            ['rejected', '{reference} was rejected', '{person} rejected {reference} ({amount}): {comment}', ['reference', 'amount', 'person', 'comment'], 'Approval refused', 'In-app, to the person who asked.'],
        ] as [$kind, $subject, $body, $placeholders, $label, $description]) {
            $this->app->make(NotificationTemplates::class)->register(new NotificationTemplateDefinition('core.approval.'.$kind, 'database', $subject, $body, $placeholders, $label, $description));
        }

        Gate::policy(ApprovalRequest::class, ApprovalRequestPolicy::class);
        $this->registerPermissions($this->app->make(PermissionRegistry::class));
        $this->registerMenu($this->app->make(MenuRegistry::class));
        $this->app->make(Onboarding::class)->register(new OnboardingStep('taxes', 'Taxes', 'Service charge and VAT on rooms and food.', 20, 'core.onboarding.taxes', fn (): bool => TaxCategory::query()->exists()));
        $this->shareNotificationBell();

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('core:purge-data-exports')->dailyAt('03:30')->withoutOverlapping()->onOneServer();
        });
    }

    private function registerSettings(Settings $settings): void
    {
        $settings->define(new SettingDefinition('core.timezone', 'Timezone', SettingType::Timezone, 'Asia/Dhaka', group: 'General',
            help: 'Times are stored in UTC and shown in this timezone.'));
        $settings->define(new SettingDefinition('core.currency', 'Base currency', SettingType::Currency, 'BDT', group: 'General',
            help: 'The currency the company reports in (ISO 4217).'));
        $settings->define(new SettingDefinition('core.date_format', 'Date format', SettingType::Select, 'd M Y', group: 'General',
            options: ['d M Y' => '27 Sep 2026', 'd/m/Y' => '27/09/2026', 'Y-m-d' => '2026-09-27', 'm/d/Y' => '09/27/2026']));

        $settings->define(new SettingDefinition('core.onboarding_dismissed', 'Setup checklist put away', SettingType::Boolean, false, group: 'General',
            help: 'Stops the reminder to finish the setup checklist on the dashboard.'));

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
            'quote' => ['Quotation', 'QUO'],
            'folio' => ['Folio', 'FOL'],
            'credit_note' => ['Credit note', 'CN'],
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
            new PermissionDefinition('core.notification-template.view', 'View email and notification templates', $managers),
            new PermissionDefinition('core.notification-template.manage', 'Change email and notification templates', $managers),
        ]);

        $permissions->register('Setup checklist', [
            new PermissionDefinition('core.onboarding.manage', 'Work through the setup checklist of a new company', $managers),
        ]);

        // The company's own subscription and data (Step 7.4); the Tenant Owner holds every permission.
        $permissions->register('Subscription', [
            new PermissionDefinition('core.subscription.view', 'See the plan, the trial and the platform invoices', [DefaultRole::GeneralManager]),
            new PermissionDefinition('core.subscription.manage', 'Cancel the subscription', []),
            new PermissionDefinition('core.data-export.manage', 'Export all of the company\'s data', []),
        ]);

        $permissions->register('Approvals', [
            new PermissionDefinition('core.approval.view', 'See the approvals that concern them and delegate their own', DefaultRole::cases()),
            new PermissionDefinition('core.approval.manage', 'Set up approval workflows and see every request', $managers),
        ]);

        $permissions->register('Taxes', [
            new PermissionDefinition('core.tax.view', 'View taxes', [DefaultRole::GeneralManager, DefaultRole::Accountant, DefaultRole::FrontOfficeManager]),
            new PermissionDefinition('core.tax.manage', 'Change taxes and tax categories', [DefaultRole::GeneralManager, DefaultRole::Accountant]),
        ]);
    }

    private function registerMenu(MenuRegistry $menu): void
    {
        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('core.onboarding', 'Setup checklist', route: 'core.onboarding.index', parent: 'setup', order: 0,
            permission: 'core.onboarding.manage', module: 'core', active: 'core.onboarding.*'));
        $menu->add(new MenuItem('core.subscription', 'Subscription', route: 'core.subscription.show', parent: 'setup', order: 1,
            permission: 'core.subscription.view', module: 'core', active: 'core.subscription.*'));
        $menu->add(new MenuItem('core.taxes', 'Taxes', route: 'core.taxes.index', parent: 'setup', order: 9,
            permission: 'core.tax.view', module: 'core', active: 'core.taxes.*'));
        $menu->add(new MenuItem('core.approvals', 'Approvals', icon: 'bi-check2-square', route: 'core.approvals.index', order: 5, permission: 'core.approval.view', module: 'core', active: 'core.approvals.*'));
        $menu->add(new MenuItem('core.approval-workflows', 'Approval workflows', route: 'core.approval-workflows.index', parent: 'setup', order: 34,
            permission: 'core.approval.manage', module: 'core', active: 'core.approval-workflows.*'));
        $menu->add(new MenuItem('core.settings', 'Settings', route: 'core.settings.index', parent: 'setup', order: 30,
            permission: 'core.setting.view', module: 'core', active: 'core.settings.*'));
        $menu->add(new MenuItem('core.sequences', 'Document numbering', route: 'core.sequences.index', parent: 'setup', order: 40,
            permission: 'core.sequence.view', module: 'core', active: 'core.sequences.*'));
        $menu->add(new MenuItem('core.notification-templates', 'Email templates', route: 'core.notification-templates.index', parent: 'setup', order: 35,
            permission: 'core.notification-template.view', module: 'core', active: 'core.notification-templates.*'));
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
