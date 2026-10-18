<?php

namespace Modules\Procurement\Providers;

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Contracts\ApprovalRegistry;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Contracts\Settings;
use Modules\Core\DTOs\ApprovalDocumentType;
use Modules\Core\DTOs\ApprovalStepDefinition;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\DTOs\SettingDefinition;
use Modules\Core\Enums\SequenceReset;
use Modules\Core\Enums\SettingScope;
use Modules\Core\Enums\SettingType;
use Modules\Procurement\Contracts\ProcurementFacts;
use Modules\Procurement\Contracts\VendorDirectory;
use Modules\Procurement\Models\GoodsReceipt;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\PurchaseReturn;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorBill;
use Modules\Procurement\Models\VendorPayment;
use Modules\Procurement\Policies\BillPolicy;
use Modules\Procurement\Policies\OrderPolicy;
use Modules\Procurement\Policies\PaymentPolicy;
use Modules\Procurement\Policies\ReceiptPolicy;
use Modules\Procurement\Policies\RequisitionPolicy;
use Modules\Procurement\Policies\ReturnPolicy;
use Modules\Procurement\Policies\RfqPolicy;
use Modules\Procurement\Policies\VendorPolicy;
use Modules\Procurement\Services\ProcurementFactsService;
use Modules\Procurement\Services\PurchaseOrderApprovalHandler;
use Modules\Procurement\Services\PurchaseRequisitionApprovalHandler;
use Modules\Procurement\Services\VendorBillApprovalHandler;
use Modules\Procurement\Services\VendorDirectoryService;
use Modules\Procurement\Services\VendorEmailComposer;
use Nwidart\Modules\Support\ModuleServiceProvider;

class ProcurementServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Procurement';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'procurement';

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

        $this->app->bind(VendorDirectory::class, VendorDirectoryService::class);
        $this->app->bind(ProcurementFacts::class, ProcurementFactsService::class);
    }

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['vendor' => Vendor::class, 'purchase_requisition' => PurchaseRequisition::class, 'rfq' => Rfq::class, 'purchase_order' => PurchaseOrder::class, 'goods_receipt' => GoodsReceipt::class, 'vendor_bill' => VendorBill::class, 'vendor_payment' => VendorPayment::class, 'purchase_return' => PurchaseReturn::class]);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(PurchaseRequisition::class, RequisitionPolicy::class);
        Gate::policy(Rfq::class, RfqPolicy::class);
        Gate::policy(PurchaseOrder::class, OrderPolicy::class);
        Gate::policy(GoodsReceipt::class, ReceiptPolicy::class);
        Gate::policy(VendorBill::class, BillPolicy::class);
        Gate::policy(VendorPayment::class, PaymentPolicy::class);
        Gate::policy(PurchaseReturn::class, ReturnPolicy::class);

        $numbers = $this->app->make(DocumentNumbers::class);
        $numbers->register(new DocumentType('vendor', 'Vendor code', 'VND', '{PREFIX}-{SEQ:4}', SequenceReset::Never));
        $numbers->register(new DocumentType('purchase_requisition', 'Purchase requisition', 'PR'));
        $numbers->register(new DocumentType('rfq', 'Request for quotation', 'RFQ'));
        $numbers->register(new DocumentType('vendor_bill', 'Vendor bill', 'VB'));
        $numbers->register(new DocumentType('vendor_payment', 'Vendor payment', 'VP'));
        $numbers->register(new DocumentType('purchase_return', 'Purchase return', 'RTV'));
        $numbers->register(new DocumentType('debit_note', 'Debit note', 'DN'));

        $approvals = $this->app->make(ApprovalRegistry::class);
        $approvals->register(new ApprovalDocumentType(
            'purchase_requisition', 'Purchase requisition', 'A department\'s request to buy items. Approved before it goes to an RFQ or a purchase order.',
            [new ApprovalStepDefinition('Department manager', 'procurement.requisition.approve', '0.00')],
        ), PurchaseRequisitionApprovalHandler::class);
        $approvals->register(new ApprovalDocumentType(
            'purchase_order', 'Purchase order', 'An order to a vendor. Approved before it is sent; large orders also need the General Manager.',
            [new ApprovalStepDefinition('Purchasing approver', 'procurement.order.approve', '0.00'), new ApprovalStepDefinition('General Manager', 'procurement.order.approve-large', '50000.00')],
        ), PurchaseOrderApprovalHandler::class);
        $approvals->register(new ApprovalDocumentType(
            'vendor_bill', 'Vendor bill', 'A vendor\'s invoice, matched to the order and the goods received. Approved before it can be paid.',
            [new ApprovalStepDefinition('Accounts approver', 'procurement.bill.approve', '0.00')],
        ), VendorBillApprovalHandler::class);

        $settings = $this->app->make(Settings::class);
        $settings->define(new SettingDefinition('procurement.over_receipt_percent', 'Over-receipt allowed (%)', SettingType::Decimal, '0', SettingScope::Property, 'Procurement',
            help: 'How far above the ordered quantity a goods receipt may go, in percent of the order line.'));
        $settings->define(new SettingDefinition('procurement.quantity_tolerance_percent', 'Billed quantity tolerance (%)', SettingType::Decimal, '0', SettingScope::Property, 'Procurement',
            help: 'How far above the received quantity a vendor bill may go before the 3-way match blocks it.'));
        $settings->define(new SettingDefinition('procurement.price_tolerance_percent', 'Billed price tolerance (%)', SettingType::Decimal, '2', SettingScope::Property, 'Procurement',
            help: 'How far above the order price a billed price may go before the bill needs the match override.'));

        $order = ['vendor', 'order_no', 'property', 'property_phone', 'property_email', 'currency', 'total', 'expected_on', 'payment_terms'];
        $rfq = ['vendor', 'rfq_no', 'property', 'property_phone', 'property_email', 'due_on'];
        $templates = $this->app->make(NotificationTemplates::class);
        $templates->register(new NotificationTemplateDefinition(VendorEmailComposer::ORDER, 'mail', 'Purchase order {order_no} from {property}',
            "Dear {vendor},\nPlease find our purchase order {order_no} attached, for a total of {currency} {total}.\nWe need the goods by {expected_on}. Payment terms: {payment_terms} days.\nPlease confirm receipt of the order and quote the order number on your delivery note and invoice.\nFor questions call {property_phone} or write to {property_email}.",
            $order, 'Purchase order', 'To the vendor, when an approved order is sent (with the order PDF).'));
        $templates->register(new NotificationTemplateDefinition(VendorEmailComposer::RFQ, 'mail', 'Request for quotation {rfq_no} from {property}',
            "Dear {vendor},\nWe would like your best prices for the items on the attached request {rfq_no}.\nPlease reply by {due_on} with your unit price and delivery time for each item.\nFor questions call {property_phone} or write to {property_email}.",
            $rfq, 'Request for quotation', 'To each vendor asked to quote (with the RFQ PDF).'));

        $gm = DefaultRole::GeneralManager;
        $buyer = DefaultRole::ProcurementOfficer;
        $viewers = [$gm, $buyer, DefaultRole::StoreKeeper, DefaultRole::FnbManager, DefaultRole::Accountant];
        $this->app->make(PermissionRegistry::class)->register('Procurement', [
            new PermissionDefinition('procurement.vendor.view', 'View vendors and their price lists', $viewers),
            new PermissionDefinition('procurement.vendor.manage', 'Add and change vendors, contacts and price lists', [$gm, $buyer]),
            new PermissionDefinition('procurement.requisition.view', 'View purchase requisitions and reorder suggestions', [...$viewers, DefaultRole::Chef, DefaultRole::Bartender, DefaultRole::HousekeepingSupervisor]),
            new PermissionDefinition('procurement.requisition.create', 'Raise purchase requisitions', [$gm, $buyer, DefaultRole::StoreKeeper, DefaultRole::FnbManager, DefaultRole::Chef, DefaultRole::HousekeepingSupervisor]),
            new PermissionDefinition('procurement.requisition.approve', 'Approve purchase requisitions', [$gm, DefaultRole::FnbManager]),
            new PermissionDefinition('procurement.rfq.view', 'View requests for quotation', $viewers),
            new PermissionDefinition('procurement.rfq.manage', 'Create and send RFQs, enter quotations and award them', [$gm, $buyer]),
            new PermissionDefinition('procurement.order.view', 'View purchase orders', $viewers),
            new PermissionDefinition('procurement.order.create', 'Write purchase orders and send them for approval', [$gm, $buyer]),
            new PermissionDefinition('procurement.order.approve', 'Approve purchase orders', [$gm, DefaultRole::Accountant]),
            new PermissionDefinition('procurement.order.approve-large', 'Approve large purchase orders', [$gm]),
            new PermissionDefinition('procurement.order.send', 'Email approved purchase orders to vendors', [$gm, $buyer]),
            new PermissionDefinition('procurement.receipt.view', 'View goods receipts', [...$viewers, DefaultRole::Chef]),
            new PermissionDefinition('procurement.receipt.create', 'Receive goods against purchase orders', [$gm, $buyer, DefaultRole::StoreKeeper]),
            new PermissionDefinition('procurement.bill.view', 'View vendor bills and payments', [$gm, $buyer, DefaultRole::Accountant]),
            new PermissionDefinition('procurement.bill.create', 'Enter and submit vendor bills', [$gm, $buyer, DefaultRole::Accountant]),
            new PermissionDefinition('procurement.bill.approve', 'Approve vendor bills', [$gm, DefaultRole::Accountant]),
            new PermissionDefinition('procurement.bill.override-match', 'Override the price check of the 3-way match', [$gm, DefaultRole::Accountant]),
            new PermissionDefinition('procurement.payment.create', 'Pay vendors', [$gm, DefaultRole::Accountant]),
            new PermissionDefinition('procurement.report.view', 'View procurement reports (open orders, vendor ledger, aging, price variance…)', [$gm, $buyer, DefaultRole::Accountant, DefaultRole::StoreKeeper]),
            new PermissionDefinition('procurement.return.view', 'View purchase returns', [...$viewers]),
            new PermissionDefinition('procurement.return.create', 'Return goods to vendors', [$gm, $buyer, DefaultRole::StoreKeeper]),
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('procurement', 'Procurement', 'bi-cart3', order: 175);
        $menu->add(new MenuItem('procurement.vendors', 'Vendors', route: 'procurement.vendors.index', parent: 'procurement', order: 10, permission: 'procurement.vendor.view', module: 'procurement', active: 'procurement.vendors.*'));
        $menu->add(new MenuItem('procurement.requisitions', 'Requisitions', route: 'procurement.requisitions.index', parent: 'procurement', order: 20, permission: 'procurement.requisition.view', module: 'procurement', active: 'procurement.requisitions.*'));
        $menu->add(new MenuItem('procurement.suggestions', 'Reorder suggestions', route: 'procurement.suggestions.index', parent: 'procurement', order: 25, permission: 'procurement.requisition.view', module: 'procurement', active: 'procurement.suggestions.*'));
        $menu->add(new MenuItem('procurement.rfqs', 'RFQs', route: 'procurement.rfqs.index', parent: 'procurement', order: 30, permission: 'procurement.rfq.view', module: 'procurement', active: 'procurement.rfqs.*'));
        $menu->add(new MenuItem('procurement.receipts', 'Goods receipts', route: 'procurement.receipts.index', parent: 'procurement', order: 50, permission: 'procurement.receipt.view', module: 'procurement', active: 'procurement.receipts.*'));
        $menu->add(new MenuItem('procurement.bills', 'Vendor bills', route: 'procurement.bills.index', parent: 'procurement', order: 60, permission: 'procurement.bill.view', module: 'procurement', active: 'procurement.bills.*'));
        $menu->add(new MenuItem('procurement.payments', 'Vendor payments', route: 'procurement.payments.index', parent: 'procurement', order: 70, permission: 'procurement.bill.view', module: 'procurement', active: 'procurement.payments.*'));
        $menu->add(new MenuItem('procurement.reports', 'Reports', route: 'procurement.reports.open-orders', parent: 'procurement', order: 90, permission: 'procurement.report.view', module: 'procurement', active: 'procurement.reports.*'));
        $menu->add(new MenuItem('procurement.returns', 'Returns', route: 'procurement.returns.index', parent: 'procurement', order: 80, permission: 'procurement.return.view', module: 'procurement', active: 'procurement.returns.*'));
        $menu->add(new MenuItem('procurement.orders', 'Purchase orders', route: 'procurement.orders.index', parent: 'procurement', order: 40, permission: 'procurement.order.view', module: 'procurement', active: 'procurement.orders.*'));
    }
}
