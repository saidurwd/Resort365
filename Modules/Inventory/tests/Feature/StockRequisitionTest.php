<?php

/*
| Requisitions, direct issues and stock counts (Step 5.4). "Done when": a requisition is approved and issued, the
| store's stock falls and the department is charged (the ledger side is in Accounting's StockPostingTest).
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Actions\SeedApprovalWorkflows;
use Modules\Core\Contracts\ApprovalEngine;
use Modules\Inventory\Actions\CancelStockCount;
use Modules\Inventory\Actions\CompleteStockCount;
use Modules\Inventory\Actions\IssueRequisition;
use Modules\Inventory\Actions\IssueStock;
use Modules\Inventory\Actions\SaveCountEntries;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Actions\StartStockCount;
use Modules\Inventory\Actions\SubmitRequisition;
use Modules\Inventory\Actions\WithdrawRequisition;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\CountStatus;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Enums\RequisitionStatus;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockCount;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\StockRequisition;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Property\Models\Department;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(function (): void {
        app(SeedStandardUnits::class)->handle();
        app(SeedApprovalWorkflows::class)->handle();
    });
});

function srStore(string $code = 'KIT'): Store
{
    return booking(fn (): Store => Store::query()->where('code', $code)->first() ?? app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => $code, 'name' => $code.' store', 'kind' => 'kitchen']));
}

function srItem(string $name, string $class = 'food'): Item
{
    return booking(function () use ($name, $class): Item {
        $category = ItemCategory::query()->firstOrCreate(['name' => ucfirst($class)], ['sort_order' => 0, 'is_active' => true, 'account_class' => $class]);

        return app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => $name, 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id')]);
    });
}

function srStock(Store $store, Item $item, string $quantity, string $cost): void
{
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $item->id, $quantity, $cost)));
}

function srHeld(Store $store, Item $item): string
{
    return booking(fn (): string => app(StockMovementContract::class)->balance($store->id, $item->id)->quantity);
}

function srDepartment(): Department
{
    return booking(fn (): Department => Department::factory()->create());
}

/** @return array{0: StockRequisition, 1: Store, 2: Item, 3: Department} */
function srPending(string $quantity = '10'): array
{
    $store = srStore();
    $rice = booking(fn (): ?Item => Item::query()->where('name', 'Rice')->first()) ?? srItem('Rice');
    srStock($store, $rice, '50', '100');
    $department = srDepartment();
    $chef = tenantUserAs(tenant('sunrise'), DefaultRole::Chef);
    $requisition = booking(fn (): StockRequisition => app(SubmitRequisition::class)->handle($store->id, $department->id, [['item_id' => $rice->id, 'quantity' => $quantity]], $chef->id, notes: 'Dinner'));

    return [$requisition, $store, $rice, $department];
}

function srApprove(StockRequisition $requisition): void
{
    $gm = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    booking(fn () => app(ApprovalEngine::class)->approve((int) $requisition->approval_request_id, $gm->id));
}

it('values a requisition, waits for approval and then lets the store issue it', function (): void {
    [$requisition, $store, $rice, $department] = srPending();

    expect($requisition->status)->toBe(RequisitionStatus::Pending)->and($requisition->estimated_value)->toBe('1000.00')->and($requisition->requisition_no)->toStartWith('SR-')
        ->and(fn () => booking(fn () => app(IssueRequisition::class)->handle($requisition)))->toThrow(StockRuleViolated::class, 'only an approved');

    srApprove($requisition);
    $approved = booking(fn (): StockRequisition => $requisition->fresh() ?? $requisition);
    expect($approved->status)->toBe(RequisitionStatus::Approved);

    $issued = booking(fn (): StockRequisition => app(IssueRequisition::class)->handle($approved));
    $issue = booking(fn (): StockIssue => StockIssue::query()->with('lines')->sole());

    expect($issued->status)->toBe(RequisitionStatus::Issued)->and(srHeld($store, $rice))->toBe('40.0000')
        ->and($issue->department_id)->toBe($department->id)->and($issue->requisition_id)->toBe($requisition->id)->and($issue->lines->sole()->total_cost)->toBe('1000.00')
        ->and(fn () => booking(fn () => app(IssueRequisition::class)->handle($issued)))->toThrow(StockRuleViolated::class);
});

it('issues less than asked, and refuses more', function (): void {
    [$requisition, $store, $rice] = srPending();
    srApprove($requisition);
    $approved = booking(fn (): StockRequisition => $requisition->fresh() ?? $requisition);

    expect(fn () => booking(fn () => app(IssueRequisition::class)->handle($approved, [$rice->id => '11'])))->toThrow(StockRuleViolated::class)
        ->and(srHeld($store, $rice))->toBe('50.0000');

    booking(fn () => app(IssueRequisition::class)->handle($approved, [$rice->id => '4']));
    expect(srHeld($store, $rice))->toBe('46.0000');
});

it('moves nothing when the store holds too little', function (): void {
    [$requisition, $store, $rice] = srPending('60');
    srApprove($requisition);
    $approved = booking(fn (): StockRequisition => $requisition->fresh() ?? $requisition);

    expect(fn () => booking(fn () => app(IssueRequisition::class)->handle($approved)))->toThrow(InsufficientStock::class)
        ->and(srHeld($store, $rice))->toBe('50.0000')
        ->and(booking(fn (): int => StockIssue::query()->count()))->toBe(0)
        ->and(booking(fn (): RequisitionStatus => StockRequisition::query()->findOrFail($requisition->id)->status))->toBe(RequisitionStatus::Approved);
});

it('marks a rejected requisition and lets its owner withdraw an open one', function (): void {
    [$requisition] = srPending();
    $gm = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    booking(fn () => app(ApprovalEngine::class)->reject((int) $requisition->approval_request_id, $gm->id, 'Not this week'));

    expect(booking(fn (): RequisitionStatus => StockRequisition::query()->findOrFail($requisition->id)->status))->toBe(RequisitionStatus::Rejected)
        ->and(fn () => booking(fn () => app(WithdrawRequisition::class)->handle($requisition->fresh() ?? $requisition, $gm->id)))->toThrow(StockRuleViolated::class);

    [$second] = srPending();
    booking(fn () => app(WithdrawRequisition::class)->handle($second, $gm->id));
    expect(booking(fn (): RequisitionStatus => StockRequisition::query()->findOrFail($second->id)->status))->toBe(RequisitionStatus::Cancelled);
});

it('refuses a requisition with a repeated, unknown or empty line', function (): void {
    $store = srStore();
    $rice = srItem('Rice');
    $department = srDepartment();
    $submit = fn (array $lines) => booking(fn () => app(SubmitRequisition::class)->handle($store->id, $department->id, $lines, 1));

    expect(fn () => $submit([]))->toThrow(StockRuleViolated::class)
        ->and(fn () => $submit([['item_id' => $rice->id, 'quantity' => '1'], ['item_id' => $rice->id, 'quantity' => '2']]))->toThrow(StockRuleViolated::class, 'once')
        ->and(fn () => $submit([['item_id' => 999999, 'quantity' => '1']]))->toThrow(StockRuleViolated::class)
        ->and(fn () => $submit([['item_id' => $rice->id, 'quantity' => '0']]))->toThrow(StockRuleViolated::class);
});

it('issues stock to a department directly, with a reason, all or nothing', function (): void {
    $store = srStore();
    $rice = srItem('Rice');
    $sugar = srItem('Sugar');
    srStock($store, $rice, '20', '100');
    srStock($store, $sugar, '1', '90');
    $department = srDepartment();
    $issue = fn (array $lines, string $reason = 'Staff canteen') => booking(fn () => app(IssueStock::class)->handle($store->id, $department->id, $lines, $reason));

    expect(fn () => $issue([['item_id' => $rice->id, 'quantity' => '5']], ''))->toThrow(StockRuleViolated::class, 'reason')
        ->and(fn () => $issue([['item_id' => $rice->id, 'quantity' => '5'], ['item_id' => $sugar->id, 'quantity' => '2']]))->toThrow(InsufficientStock::class)
        ->and(srHeld($store, $rice))->toBe('20.0000');

    $done = $issue([['item_id' => $rice->id, 'quantity' => '5']]);
    expect($done->totalCost)->toBe('500.00')->and($done->issueNo)->toStartWith('ISS-')->and(srHeld($store, $rice))->toBe('15.0000');
});

it('counts a store, allows for movements during the count, and adjusts by the differences', function (): void {
    $store = srStore();
    $rice = srItem('Rice');
    $sugar = srItem('Sugar');
    $salt = srItem('Salt');
    srStock($store, $rice, '20', '100');
    srStock($store, $sugar, '10', '90');
    srStock($store, $salt, '5', '40');

    $count = booking(fn (): StockCount => app(StartStockCount::class)->handle($store->id, blind: true));
    expect($count->count_no)->toStartWith('SC-')->and($count->lines)->toHaveCount(3)
        ->and(fn () => booking(fn () => app(StartStockCount::class)->handle($store->id)))->toThrow(StockRuleViolated::class, 'in progress');

    // 2 kg of rice leave the store while the count is open; the shelf then shows 17 less a loss of 1.
    booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Issue, $store->id, $rice->id, '2')));
    booking(fn () => app(SaveCountEntries::class)->handle($count, [$rice->id => '16', $sugar->id => '12', $salt->id => null]));

    expect(booking(fn (): int => app(CompleteStockCount::class)->handle($count->fresh() ?? $count)))->toBe(2)
        ->and(srHeld($store, $rice))->toBe('16.0000')->and(srHeld($store, $sugar))->toBe('12.0000')->and(srHeld($store, $salt))->toBe('5.0000');

    $done = booking(fn (): StockCount => StockCount::query()->with('lines')->findOrFail($count->id));
    expect($done->status)->toBe(CountStatus::Completed)->and($done->lines->firstWhere('item_id', $rice->id)->variance_quantity)->toBe('-2.0000')
        ->and($done->lines->firstWhere('item_id', $sugar->id)->variance_quantity)->toBe('2.0000')->and($done->lines->firstWhere('item_id', $salt->id)->adjustment_movement_id)->toBeNull()
        ->and(fn () => booking(fn () => app(CompleteStockCount::class)->handle($done)))->toThrow(StockRuleViolated::class);
});

it('refuses to complete an empty count, and cancels one without touching stock', function (): void {
    $store = srStore();
    $rice = srItem('Rice');
    srStock($store, $rice, '20', '100');
    $count = booking(fn (): StockCount => app(StartStockCount::class)->handle($store->id));

    expect(fn () => booking(fn () => app(CompleteStockCount::class)->handle($count)))->toThrow(StockRuleViolated::class, 'Enter what was counted')
        ->and(fn () => booking(fn () => app(SaveCountEntries::class)->handle($count, [$rice->id => '-1'])))->toThrow(StockRuleViolated::class);

    booking(fn () => app(CancelStockCount::class)->handle($count));
    expect(srHeld($store, $rice))->toBe('20.0000')->and(booking(fn (): CountStatus => StockCount::query()->findOrFail($count->id)->status))->toBe(CountStatus::Cancelled)
        ->and(fn () => booking(fn () => app(SaveCountEntries::class)->handle($count->fresh() ?? $count, [$rice->id => '3'])))->toThrow(StockRuleViolated::class);
    booking(fn () => app(StartStockCount::class)->handle($store->id));
});

it('takes a requisition through the screens', function (): void {
    staffUser(DefaultRole::GeneralManager);
    $store = srStore();
    $rice = srItem('Rice');
    srStock($store, $rice, '50', '100');
    $department = srDepartment();

    get(tenantUrl('sunrise', '/inventory/requisitions'))->assertOk()->assertSeeHtml('data-new-requisition');
    get(tenantUrl('sunrise', '/inventory/requisitions/new'))->assertOk();
    post(tenantUrl('sunrise', '/inventory/requisitions'), ['store_id' => $store->id, 'department_id' => $department->id, 'lines' => []])->assertSessionHasErrors('lines');
    post(tenantUrl('sunrise', '/inventory/requisitions'), ['store_id' => $store->id, 'department_id' => $department->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '10']]])->assertSessionHasNoErrors()->assertSessionHas('success');

    $requisition = booking(fn (): StockRequisition => StockRequisition::query()->sole());
    get(tenantUrl('sunrise', "/inventory/requisitions/{$requisition->id}"))->assertOk()->assertSee('1,000.00')->assertDontSeeHtml('data-issue');

    srApprove($requisition);
    get(tenantUrl('sunrise', "/inventory/requisitions/{$requisition->id}"))->assertOk()->assertSeeHtml('data-issue');
    post(tenantUrl('sunrise', "/inventory/requisitions/{$requisition->id}/issue"), ['quantities' => [$rice->id => '6']])->assertSessionHas('success');
    expect(srHeld($store, $rice))->toBe('44.0000');
    post(tenantUrl('sunrise', "/inventory/requisitions/{$requisition->id}/issue"), [])->assertSessionHas('error');
});

it('issues directly and counts through the screens', function (): void {
    staffUser(DefaultRole::StoreKeeper);
    $store = srStore();
    $rice = srItem('Rice');
    srStock($store, $rice, '20', '100');
    $department = srDepartment();

    get(tenantUrl('sunrise', '/inventory/issues/new'))->assertOk();
    post(tenantUrl('sunrise', '/inventory/issues'), ['store_id' => $store->id, 'department_id' => $department->id, 'lines' => [['item_id' => $rice->id, 'quantity' => '4']]])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', '/inventory/issues'), ['store_id' => $store->id, 'department_id' => $department->id, 'reason' => 'Canteen', 'lines' => [['item_id' => $rice->id, 'quantity' => '4']]])->assertSessionHas('success');
    expect(srHeld($store, $rice))->toBe('16.0000');

    post(tenantUrl('sunrise', '/inventory/counts'), ['store_id' => $store->id, 'blind' => '1'])->assertSessionHas('success');
    $count = booking(fn (): StockCount => StockCount::query()->sole());
    get(tenantUrl('sunrise', '/inventory/counts'))->assertOk()->assertSeeHtml('data-count="'.$count->count_no.'"');
    get(tenantUrl('sunrise', "/inventory/counts/{$count->id}"))->assertOk()->assertSeeHtml('data-save-counts')->assertDontSee('Expected');
    get(tenantUrl('sunrise', "/inventory/counts/{$count->id}/sheet"))->assertOk()->assertSee('Rice');
    put(tenantUrl('sunrise', "/inventory/counts/{$count->id}"), ['entries' => [$rice->id => '15']])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/inventory/counts/{$count->id}/complete"))->assertSessionHas('success');
    expect(srHeld($store, $rice))->toBe('15.0000');
    get(tenantUrl('sunrise', "/inventory/counts/{$count->id}"))->assertOk()->assertSee('Expected')->assertSee('-100.00');
});

it('keeps requisitions, issues and counts to the people who hold the permissions', function (): void {
    $store = srStore();
    srItem('Rice');
    $department = srDepartment();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/inventory/requisitions'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/requisitions/new'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/issues/new'))->assertForbidden();
    get(tenantUrl('sunrise', '/inventory/counts'))->assertForbidden();
    post(tenantUrl('sunrise', '/inventory/counts'), ['store_id' => $store->id])->assertForbidden();

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/inventory/requisitions'))->assertOk()->assertDontSeeHtml('data-new-requisition');
    get(tenantUrl('sunrise', '/inventory/counts'))->assertOk();
    post(tenantUrl('sunrise', '/inventory/issues'), ['store_id' => $store->id, 'department_id' => $department->id, 'reason' => 'x', 'lines' => []])->assertForbidden();
});
