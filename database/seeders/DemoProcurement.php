<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Inventory\Contracts\ItemCatalog;
use Modules\Inventory\Contracts\ReorderSuggestions;
use Modules\Inventory\Contracts\StoreDirectory;
use Modules\Procurement\Actions\AwardRfq;
use Modules\Procurement\Actions\CreateRequisitionFromSuggestions;
use Modules\Procurement\Actions\CreateRfq;
use Modules\Procurement\Actions\RecordQuotation;
use Modules\Procurement\Actions\SavePurchaseOrder;
use Modules\Procurement\Actions\SavePurchaseRequisition;
use Modules\Procurement\Actions\SaveVendor;
use Modules\Procurement\Actions\SaveVendorContact;
use Modules\Procurement\Actions\SaveVendorPrice;
use Modules\Procurement\Actions\SendPurchaseOrder;
use Modules\Procurement\Actions\SendRfq;
use Modules\Procurement\Actions\SubmitPurchaseOrder;
use Modules\Procurement\DTOs\NewPurchaseOrder;
use Modules\Procurement\Models\PurchaseRequisition;
use Modules\Procurement\Models\Vendor;
use Modules\Property\Contracts\DepartmentDirectory;

/**
 * Procurement demo (Step 5.5), Rodela only: five vendors with contacts and price lists, a requisition raised from the
 * reorder levels and approved, a second one that went through an RFQ (two quotations compared, Bay Fisheries won) into
 * a large order approved by two people and emailed, and a smaller order still waiting for approval.
 */
final class DemoProcurement
{
    /**
     * vendor code => [name, email, terms, rating, contact, prices (item name => price)].
     *
     * @var array<string, array{0: string, 1: string, 2: int, 3: int, 4: string, 5: array<string, string>}>
     */
    private const array VENDORS = [
        'FRESHFARM' => ['Fresh Farm Supplies', 'orders@freshfarm.example', 14, 4, 'Anwar Hossain', ['Chicken whole' => '275', 'Beef boneless' => '880', 'Hilsa fish' => '1850', 'Tiger prawn' => '1550', 'Eggs' => '14.5', 'Tomato' => '68', 'Onion' => '58']],
        'BAYFISH' => ['Bay Fisheries', 'sales@bayfisheries.example', 7, 5, 'Mizanur Rahman', ['Hilsa fish' => '1790', 'Tiger prawn' => '1580', 'Rui fish' => '410', 'Crab' => '690', 'Chicken whole' => '290']],
        'DHAKADRY' => ['Dhaka Dry Goods', 'sales@dhakadry.example', 30, 4, 'Shafiqul Islam', ['Basmati rice' => '160', 'Miniket rice' => '76', 'Wheat flour' => '56', 'Sugar' => '118', 'Soybean oil' => '185', 'Eggs' => '14']],
        'COOLDRINK' => ['Cool Drinks Distribution', 'orders@cooldrinks.example', 15, 3, 'Rafiq Ahmed', ['Coca-Cola 500 ml' => '33', 'Sprite 500 ml' => '33', 'Mineral water 500 ml' => '12.5']],
        'CLEANCARE' => ['Clean & Care Hospitality', 'info@cleancare.example', 30, 4, 'Nasrin Akter', ['Soap bar 25 g' => '8.5', 'Shampoo 30 ml' => '11', 'Toilet roll' => '20', 'Floor cleaner' => '145']],
    ];

    public static function seed(Tenant $tenant, int $propertyId, string $domain): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId, $domain): void {
            $users = collect(app(UserDirectory::class)->all());
            $user = fn (string $mailbox): ?int => $users->firstWhere('email', $mailbox.'@'.$domain)?->id;
            [$buyer, $keeper, $chef, $fnb, $accountant, $gm] = [$user('procurement'), $user('store'), $user('chef'), $user('fnb'), $user('accountant'), $user('gm')];
            $items = app(ItemCatalog::class);
            $itemId = fn (string $name): ?int => ($items->search($name, 1)[0] ?? null)?->id;

            if (Vendor::query()->exists() || in_array(null, [$buyer, $keeper, $chef, $fnb, $accountant, $gm], true)) {
                return;
            }

            $vendors = [];

            foreach (self::VENDORS as $code => [$name, $email, $terms, $rating, $contact, $prices]) {
                $vendor = app(SaveVendor::class)->handle(null, ['code' => $code, 'name' => $name, 'email' => $email, 'phone' => '01'.random_int(700000000, 799999999), 'currency' => 'BDT', 'payment_terms_days' => $terms, 'rating' => $rating, 'status' => 'active', 'address' => 'Dhaka, Bangladesh']);
                app(SaveVendorContact::class)->handle($vendor, null, ['name' => $contact, 'role' => 'Sales', 'email' => $email, 'is_primary' => true]);

                foreach ($prices as $itemName => $price) {
                    $id = $itemId($itemName);

                    if ($id !== null) {
                        app(SaveVendorPrice::class)->handle($vendor, null, ['item_id' => $id, 'price' => $price]);
                    }
                }

                $vendors[$code] = $vendor;
            }

            $departments = collect(app(DepartmentDirectory::class)->all(false))->keyBy('code');
            $approve = fn (PurchaseRequisition $requisition, int $by) => app(ApprovalEngine::class)->approve((int) $requisition->approval_request_id, $by);
            $main = collect(app(StoreDirectory::class)->forProperty($propertyId))->firstWhere('code', 'MAIN');

            // A requisition from the reorder levels, approved and waiting to be ordered.
            $below = collect(app(ReorderSuggestions::class)->forProperty($propertyId))->pluck('itemId')->unique()->take(3)->values()->all();

            if ($below !== []) {
                $reorder = app(CreateRequisitionFromSuggestions::class)->handle($propertyId, $below, $keeper, departmentId: $departments['STR']->id ?? null);
                $approve($reorder, $fnb);
            }

            // A kitchen requisition that goes through an RFQ.
            $kitchen = app(SavePurchaseRequisition::class)->handle($propertyId, $departments['FBP']->id ?? null, [
                ['item_id' => (int) $itemId('Chicken whole'), 'quantity' => '40'], ['item_id' => (int) $itemId('Hilsa fish'), 'quantity' => '20'], ['item_id' => (int) $itemId('Tiger prawn'), 'quantity' => '10'],
            ], $chef, notes: 'Weekend buffet');
            $approve($kitchen, $fnb);
            $rfq = app(CreateRfq::class)->handle($kitchen->fresh() ?? $kitchen, [$vendors['FRESHFARM']->id, $vendors['BAYFISH']->id], $buyer, now()->addDays(3)->toDateString());
            app(SendRfq::class)->handle($rfq);
            $rfq = $rfq->fresh() ?? $rfq;
            $quote = fn (string $code, array $prices) => app(RecordQuotation::class)->handle($rfq, $vendors[$code]->id, collect($prices)->map(fn (string $price, string $name): array => ['item_id' => (int) $itemId($name), 'unit_price' => $price, 'lead_days' => 2])->values()->all(), 'Q-'.$code);
            $quote('FRESHFARM', ['Chicken whole' => '275', 'Hilsa fish' => '1850', 'Tiger prawn' => '1550']);
            $winner = $quote('BAYFISH', ['Chicken whole' => '290', 'Hilsa fish' => '1790', 'Tiger prawn' => '1580']);

            if ($main !== null) {
                $order = app(AwardRfq::class)->handle($rfq, $winner, $main->id, $buyer, now()->addDays(4)->toDateString());
                $order = app(SubmitPurchaseOrder::class)->handle($order, $buyer);
                app(ApprovalEngine::class)->approve((int) $order->approval_request_id, $accountant);
                app(ApprovalEngine::class)->approve((int) $order->approval_request_id, $gm);
                app(SendPurchaseOrder::class)->handle($order->fresh() ?? $order);

                // A smaller order still waiting for its approver.
                $dry = $vendors['DHAKADRY'];
                $lines = collect(['Basmati rice' => ['100', '160'], 'Sugar' => ['50', '118'], 'Soybean oil' => ['40', '185']])->map(fn (array $row, string $name): array => ['item_id' => (int) $itemId($name), 'quantity' => $row[0], 'unit_price' => $row[1]])->values()->all();
                $pending = app(SavePurchaseOrder::class)->handle(null, new NewPurchaseOrder($propertyId, $dry->id, $main->id, $lines, $buyer, $departments['STR']->id ?? null, null, now()->addDays(7)->toDateString(), 'Monthly dry goods'));
                app(SubmitPurchaseOrder::class)->handle($pending, $buyer);
            }
        });
    }
}
