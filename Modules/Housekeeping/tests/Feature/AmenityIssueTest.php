<?php

/*
| Housekeeping (Step 5.4): an attendant issues amenities from the housekeeping store on My tasks; Inventory issues them
| to the Housekeeping department.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockIssue;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Property\Models\Department;
use Modules\Property\Models\Room;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

/** @return array{0: Store, 1: Item, 2: Department} */
function amenitySetup(bool $withStore = true): array
{
    return booking(function () use ($withStore): array {
        $department = Department::factory()->create();
        $store = app(SaveStore::class)->handle(bookingIds()['property'], null, ['code' => 'HK', 'name' => 'Housekeeping store', 'kind' => $withStore ? 'housekeeping' : 'main', 'department_id' => $department->id]);
        $category = ItemCategory::query()->create(['name' => 'Housekeeping', 'sort_order' => 0, 'is_active' => true, 'account_class' => 'housekeeping']);
        $soap = app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => 'Soap bar', 'base_unit_id' => Unit::query()->where('code', 'pcs')->value('id')]);
        app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $soap->id, '100', '9'));

        return [$store, $soap, $department];
    });
}

it('issues amenities from the housekeeping store to the Housekeeping department', function (): void {
    [$store, $soap, $department] = amenitySetup();
    staffUser(DefaultRole::HousekeepingSupervisor);
    $room = booking(fn (): int => Room::query()->where('number', '401')->value('id'));

    get(tenantUrl('sunrise', '/housekeeping/my-tasks'))->assertOk()->assertSeeHtml('data-amenities')->assertSeeHtml('data-amenity="'.$soap->sku.'"');
    post(tenantUrl('sunrise', '/housekeeping/amenities'), ['room_id' => $room, 'items' => [$soap->id => '12']])->assertSessionHas('success');

    $issue = booking(fn (): StockIssue => StockIssue::query()->with('lines')->sole());
    expect($issue->store_id)->toBe($store->id)->and($issue->department_id)->toBe($department->id)->and($issue->source_type)->toBe('housekeeping_task')
        ->and($issue->notes)->toContain('401')->and($issue->lines->sole()->total_cost)->toBe('108.00')
        ->and(booking(fn (): string => app(StockMovementContract::class)->balance($store->id, $soap->id)->quantity))->toBe('88.0000');
});

it('asks for a quantity, and refuses more than the store holds', function (): void {
    [$store, $soap] = amenitySetup();
    staffUser(DefaultRole::HousekeepingSupervisor);

    post(tenantUrl('sunrise', '/housekeeping/amenities'), ['items' => [$soap->id => '']])->assertSessionHas('error');
    post(tenantUrl('sunrise', '/housekeeping/amenities'), ['items' => [$soap->id => '500']])->assertSessionHas('error');
    expect(booking(fn (): int => StockIssue::query()->count()))->toBe(0)
        ->and(booking(fn (): string => app(StockMovementContract::class)->balance($store->id, $soap->id)->quantity))->toBe('100.0000');
});

it('keeps the panel to attendants', function (): void {
    [, $soap] = amenitySetup();
    staffUser(DefaultRole::FrontDeskAgent);

    post(tenantUrl('sunrise', '/housekeeping/amenities'), ['items' => [$soap->id => '3']])->assertForbidden();
});
