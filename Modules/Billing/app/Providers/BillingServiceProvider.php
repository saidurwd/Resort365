<?php

namespace Modules\Billing\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Contracts\CityLedgerAccounts;
use Modules\Billing\Contracts\DailyTakings;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\Contracts\FolioReferenceLinks;
use Modules\Billing\Contracts\FolioSettlement;
use Modules\Billing\Contracts\LedgerFacts;
use Modules\Billing\Models\CashierShift;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\CreditNote;
use Modules\Billing\Models\ExtraService;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Models\FolioRoutingRule;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Policies\CashierShiftPolicy;
use Modules\Billing\Policies\ChargeCodePolicy;
use Modules\Billing\Policies\ExtraServicePolicy;
use Modules\Billing\Policies\FolioPolicy;
use Modules\Billing\Policies\InvoicePolicy;
use Modules\Billing\Policies\PaymentPolicy;
use Modules\Billing\Services\CityLedgerAccountsService;
use Modules\Billing\Services\DailyTakingsService;
use Modules\Billing\Services\FolioPostingService;
use Modules\Billing\Services\FolioSettlementService;
use Modules\Billing\Services\FoliosTab;
use Modules\Billing\Services\LedgerFactsService;
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
        $this->app->bind(CityLedgerAccounts::class, CityLedgerAccountsService::class);
        $this->app->singleton(FolioReferenceLinks::class);
        $this->app->bind(FolioSettlement::class, FolioSettlementService::class);
        $this->app->bind(DailyTakings::class, DailyTakingsService::class);
        $this->app->bind(LedgerFacts::class, LedgerFactsService::class);
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
            'invoice' => Invoice::class,
            'invoice_line' => InvoiceLine::class,
            'credit_note' => CreditNote::class,
            'city_ledger_entry' => CityLedgerEntry::class,
            'cashier_shift' => CashierShift::class,
        ]);
        Gate::policy(CashierShift::class, CashierShiftPolicy::class);
        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(Folio::class, FolioPolicy::class);
        Gate::policy(ChargeCode::class, ChargeCodePolicy::class);
        Gate::policy(ExtraService::class, ExtraServicePolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);

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
            new PermissionDefinition('billing.invoice.view', 'View invoices and credit notes', [...$desk, DefaultRole::Accountant]),
            new PermissionDefinition('billing.credit-note.issue', 'Issue credit notes', [DefaultRole::GeneralManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.refund.issue', 'Pay refunds', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.city-ledger.view', 'View the city ledger', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.city-ledger.manage', 'Receive city-ledger payments', [DefaultRole::GeneralManager, DefaultRole::Accountant]),
            new PermissionDefinition('billing.shift.open', 'Open and close own cashier shift', [DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::OutletCashier]),
            new PermissionDefinition('billing.shift.view', 'View every cashier shift and its variance', [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::Accountant]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('billing', 'Billing', 'bi-cash-stack', order: 300);
        $menu->add(new MenuItem('billing.city-ledger', 'City ledger', route: 'billing.city-ledger.index', parent: 'billing', order: 10,
            permission: 'billing.city-ledger.view', module: 'billing', active: 'billing.city-ledger.*'));
        $menu->add(new MenuItem('billing.my-shift', 'My cashier shift', route: 'billing.shifts.mine', parent: 'billing', order: 5,
            permission: 'billing.shift.open', module: 'billing', active: 'billing.shifts.mine'));
        $menu->add(new MenuItem('billing.shifts', 'Cashier shifts', route: 'billing.shifts.index', parent: 'billing', order: 6,
            permission: 'billing.shift.view', module: 'billing', active: ['billing.shifts.index', 'billing.shifts.show']));
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

        $settings->define(new SettingDefinition('billing.cash_denominations', 'Cash denominations', SettingType::Text, '1000,500,200,100,50,20,10,5,2,1',
            SettingScope::Tenant, 'Billing', help: 'Notes and coins counted when a cashier shift is closed, separated by commas.', rules: ['regex:/^\\s*\\d+(\\.\\d+)?(\\s*,\\s*\\d+(\\.\\d+)?)*\\s*$/']));

        // The Payments tab of the reservation page (Reservation may not call Billing itself).
        $this->app->make(ReservationTabs::class)->add(new ReservationTab('payments', 'Payments', 'bi-credit-card', 'billing.payment.view', 'billing',
            fn (int $reservationId) => $this->app->make(PaymentsTab::class)->render($reservationId), order: 40));
        $this->app->make(ReservationTabs::class)->add(new ReservationTab('folios', 'Folios', 'bi-receipt', 'billing.folio.view', 'billing',
            fn (int $reservationId) => $this->app->make(FoliosTab::class)->render($reservationId), order: 45));
    }
}
