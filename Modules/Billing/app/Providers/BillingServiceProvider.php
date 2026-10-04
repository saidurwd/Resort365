<?php

namespace Modules\Billing\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\ExtraService;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\FolioRoutingRule;
use Modules\Billing\Models\Payment;
use Modules\Billing\Policies\ChargeCodePolicy;
use Modules\Billing\Policies\ExtraServicePolicy;
use Modules\Billing\Policies\FolioPolicy;
use Modules\Billing\Policies\PaymentPolicy;
use Modules\Billing\Services\FolioPostingService;
use Modules\Billing\Services\FoliosTab;
use Modules\Billing\Services\PaymentsTab;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Reservation\Contracts\ReservationTabs;
use Modules\Reservation\DTOs\ReservationTab;
use Nwidart\Modules\Support\ModuleServiceProvider;

class BillingServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Billing';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'billing';

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

        $this->app->bind(FolioPostingContract::class, FolioPostingService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap([
            'payment' => Payment::class,
            'charge_code' => ChargeCode::class,
            'extra_service' => ExtraService::class,
            'folio' => Folio::class,
            'folio_line' => FolioLine::class,
            'folio_routing_rule' => FolioRoutingRule::class,
        ]);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Folio::class, FolioPolicy::class);
        Gate::policy(ChargeCode::class, ChargeCodePolicy::class);
        Gate::policy(ExtraService::class, ExtraServicePolicy::class);

        $desk = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent];

        $this->app->make(PermissionRegistry::class)->register('Billing', [
            new PermissionDefinition('billing.payment.view', 'View payments and receipts', [...$desk, DefaultRole::Accountant]),
            new PermissionDefinition('billing.payment.create', 'Take payments', $desk),
            new PermissionDefinition('billing.folio.view', 'View folios', [...$desk, DefaultRole::Accountant]),
            new PermissionDefinition('billing.folio.post', 'Post charges to folios', $desk),
            new PermissionDefinition('billing.folio.adjust', 'Post folio adjustments', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.folio.void', 'Void folio lines', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
            new PermissionDefinition('billing.charge-code.view', 'View charge codes', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.charge-code.manage', 'Change charge codes', [DefaultRole::GeneralManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.extra-service.view', 'View the extras catalogue', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.extra-service.manage', 'Change the extras catalogue', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('setup', 'Setup', 'bi-gear', order: 900);
        $menu->add(new MenuItem('billing.charge-codes', 'Charge codes', route: 'billing.charge-codes.index', parent: 'setup', order: 36,
            permission: 'billing.charge-code.view', module: 'billing', active: 'billing.charge-codes.*'));
        $menu->add(new MenuItem('billing.extra-services', 'Extras', route: 'billing.extra-services.index', parent: 'setup', order: 37,
            permission: 'billing.extra-service.view', module: 'billing', active: 'billing.extra-services.*'));

        $settings = $this->app->make(Settings::class);
        $settings->define(new SettingDefinition('billing.guest_credit_limit', 'Guest folio credit limit', SettingType::Decimal, '0', SettingScope::Property,
            'Billing', help: 'Charges from other departments (e.g. restaurant) are refused above this balance. 0 = no limit.', rules: ['min:0']));
        $settings->define(new SettingDefinition('billing.revenue_recognition', 'Room revenue recognised', SettingType::Select, 'nightly', SettingScope::Tenant,
            'Billing', help: 'Nightly at night audit (accrual), or at check-out (ARCHITECTURE §7, Q8).', options: ['nightly' => 'Nightly at night audit', 'at_checkout' => 'At check-out']));

        // The Payments tab of the reservation page (Reservation may not call Billing itself).
        $this->app->make(ReservationTabs::class)->add(new ReservationTab('payments', 'Payments', 'bi-credit-card', 'billing.payment.view', 'billing',
            fn (int $reservationId) => $this->app->make(PaymentsTab::class)->render($reservationId), order: 40));
        $this->app->make(ReservationTabs::class)->add(new ReservationTab('folios', 'Folios', 'bi-receipt', 'billing.folio.view', 'billing',
            fn (int $reservationId) => $this->app->make(FoliosTab::class)->render($reservationId), order: 45));
    }
}
