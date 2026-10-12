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
use Modules\Core\DTOs\ApprovalDocumentType;
use Modules\Core\DTOs\ApprovalStepDefinition;
use Modules\Core\DTOs\DocumentType;
use Modules\Core\DTOs\NotificationTemplateDefinition;
use Modules\Core\Enums\SequenceReset;
use Modules\Procurement\Models\PurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Rfq;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Policies\OrderPolicy;
use Modules\Procurement\Policies\RequisitionPolicy;
use Modules\Procurement\Policies\RfqPolicy;
use Modules\Procurement\Policies\VendorPolicy;
use Modules\Procurement\Services\PurchaseOrderApprovalHandler;
use Modules\Procurement\Services\PurchaseRequisitionApprovalHandler;
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

    public function boot(): void
    {
        parent::boot();

        Relation::morphMap(['vendor' => Vendor::class, 'purchase_requisition' => PurchaseRequisition::class, 'rfq' => Rfq::class, 'purchase_order' => PurchaseOrder::class]);
        Gate::policy(Vendor::class, VendorPolicy::class);
        Gate::policy(PurchaseRequisition::class, RequisitionPolicy::class);
        Gate::policy(Rfq::class, RfqPolicy::class);
        Gate::policy(PurchaseOrder::class, OrderPolicy::class);

        $numbers = $this->app->make(DocumentNumbers::class);
        $numbers->register(new DocumentType('vendor', 'Vendor code', 'VND', '{PREFIX}-{SEQ:4}', SequenceReset::Never));
        $numbers->register(new DocumentType('purchase_requisition', 'Purchase requisition', 'PR'));
        $numbers->register(new DocumentType('rfq', 'Request for quotation', 'RFQ'));

        $approvals = $this->app->make(ApprovalRegistry::class);
        $approvals->register(new ApprovalDocumentType(
            'purchase_requisition', 'Purchase requisition', 'A department\'s request to buy items. Approved before it goes to an RFQ or a purchase order.',
            [new ApprovalStepDefinition('Department manager', 'procurement.requisition.approve', '0.00')],
        ), PurchaseRequisitionApprovalHandler::class);
        $approvals->register(new ApprovalDocumentType(
            'purchase_order', 'Purchase order', 'An order to a vendor. Approved before it is sent; large orders also need the General Manager.',
            [new ApprovalStepDefinition('Purchasing approver', 'procurement.order.approve', '0.00'), new ApprovalStepDefinition('General Manager', 'procurement.order.approve-large', '50000.00')],
        ), PurchaseOrderApprovalHandler::class);

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
        ]);

        $menu = $this->app->make(MenuRegistry::class);
        $menu->group('procurement', 'Procurement', 'bi-cart3', order: 175);
        $menu->add(new MenuItem('procurement.vendors', 'Vendors', route: 'procurement.vendors.index', parent: 'procurement', order: 10, permission: 'procurement.vendor.view', module: 'procurement', active: 'procurement.vendors.*'));
        $menu->add(new MenuItem('procurement.requisitions', 'Requisitions', route: 'procurement.requisitions.index', parent: 'procurement', order: 20, permission: 'procurement.requisition.view', module: 'procurement', active: 'procurement.requisitions.*'));
        $menu->add(new MenuItem('procurement.suggestions', 'Reorder suggestions', route: 'procurement.suggestions.index', parent: 'procurement', order: 25, permission: 'procurement.requisition.view', module: 'procurement', active: 'procurement.suggestions.*'));
        $menu->add(new MenuItem('procurement.rfqs', 'RFQs', route: 'procurement.rfqs.index', parent: 'procurement', order: 30, permission: 'procurement.rfq.view', module: 'procurement', active: 'procurement.rfqs.*'));
        $menu->add(new MenuItem('procurement.orders', 'Purchase orders', route: 'procurement.orders.index', parent: 'procurement', order: 40, permission: 'procurement.order.view', module: 'procurement', active: 'procurement.orders.*'));
    }
}
