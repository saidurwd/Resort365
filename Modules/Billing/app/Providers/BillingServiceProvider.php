<?php

namespace Modules\Billing\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Models\Payment;
use Modules\Billing\Policies\PaymentPolicy;
use Modules\Billing\Services\PaymentsTab;
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

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['payment' => Payment::class]);
        Gate::policy(Payment::class, PaymentPolicy::class);

        $desk = [DefaultRole::GeneralManager, DefaultRole::FrontOfficeManager, DefaultRole::FrontDeskAgent, DefaultRole::ReservationAgent];

        $this->app->make(PermissionRegistry::class)->register('Billing', [
            new PermissionDefinition('billing.payment.view', 'View payments and receipts', [...$desk, DefaultRole::Accountant]),
            new PermissionDefinition('billing.payment.create', 'Take payments', $desk),
        ]);

        // The Payments tab of the reservation page (Reservation may not call Billing itself).
        $this->app->make(ReservationTabs::class)->add(new ReservationTab('payments', 'Payments', 'bi-credit-card', 'billing.payment.view', 'billing',
            fn (int $reservationId) => $this->app->make(PaymentsTab::class)->render($reservationId), order: 40));
    }
}
