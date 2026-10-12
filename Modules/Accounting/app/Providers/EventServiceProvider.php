<?php

namespace Modules\Accounting\Providers;

use App\Support\Tenancy\Events\TenantCreated;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Accounting\Listeners\PostLedgerEntries;
use Modules\Accounting\Listeners\SeedChartForNewTenant;
use Modules\Billing\Events\CityLedgerTransferred;
use Modules\Billing\Events\FolioChargeVoided;
use Modules\Billing\Events\InvoiceIssued;
use Modules\Billing\Events\PaymentReceived;
use Modules\Billing\Events\RefundIssued;
use Modules\FrontOffice\Events\NightAuditCompleted;
use Modules\Inventory\Events\StockAdjusted;
use Modules\Inventory\Events\StockIssued;
use Modules\Procurement\Events\GoodsReceived;
use Modules\Procurement\Events\PurchaseReturned;
use Modules\Procurement\Events\VendorBillApproved;
use Modules\Procurement\Events\VendorPaymentMade;
use Modules\Reservation\Events\ReservationCancelled;
use Modules\Restaurant\Events\PosSessionClosed;
use Modules\Restaurant\Events\RestaurantBillSettled;
use Modules\Restaurant\Events\RestaurantBillVoided;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        TenantCreated::class => [SeedChartForNewTenant::class],
        PaymentReceived::class => [PostLedgerEntries::class.'@onPaymentReceived'],
        RefundIssued::class => [PostLedgerEntries::class.'@onRefundIssued'],
        NightAuditCompleted::class => [PostLedgerEntries::class.'@onNightAuditCompleted'],
        InvoiceIssued::class => [PostLedgerEntries::class.'@onInvoiceIssued'],
        CityLedgerTransferred::class => [PostLedgerEntries::class.'@onCityLedgerTransferred'],
        FolioChargeVoided::class => [PostLedgerEntries::class.'@onFolioChargeVoided'],
        ReservationCancelled::class => [PostLedgerEntries::class.'@onReservationCancelled'],
        RestaurantBillSettled::class => [PostLedgerEntries::class.'@onRestaurantBillSettled'],
        RestaurantBillVoided::class => [PostLedgerEntries::class.'@onRestaurantBillVoided'],
        PosSessionClosed::class => [PostLedgerEntries::class.'@onPosSessionClosed'],
        StockIssued::class => [PostLedgerEntries::class.'@onStockIssued'],
        StockAdjusted::class => [PostLedgerEntries::class.'@onStockAdjusted'],
        GoodsReceived::class => [PostLedgerEntries::class.'@onGoodsReceived'],
        VendorBillApproved::class => [PostLedgerEntries::class.'@onVendorBillApproved'],
        VendorPaymentMade::class => [PostLedgerEntries::class.'@onVendorPaymentMade'],
        PurchaseReturned::class => [PostLedgerEntries::class.'@onPurchaseReturned'],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;

    /**
     * Discover listeners in this module only (Laravel's default is the application's app/Listeners).
     *
     * @return array<int, string>
     */
    protected function discoverEventsWithin(): array
    {
        return [__DIR__.'/../Listeners'];
    }

    /**
     * Configure the proper event listeners for email verification.
     */
    protected function configureEmailVerification(): void {}
}
