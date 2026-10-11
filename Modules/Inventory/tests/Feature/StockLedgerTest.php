<?php

/*
| The stock ledger (Step 5.3): movements, weighted-average cost, the negative-stock rule, opening balances, adjustments,
| reversals and two-step transfers. "Done when": weighted-average cost is correct across receipts at different prices.
*/
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Modules\Core\Contracts\Settings;
use Modules\Inventory\Actions\AdjustStock;
use Modules\Inventory\Actions\CancelTransfer;
use Modules\Inventory\Actions\DispatchTransfer;
use Modules\Inventory\Actions\ReceiveTransfer;
use Modules\Inventory\Actions\RecordOpeningBalance;
use Modules\Inventory\Actions\ReverseMovement;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Actions\SeedStandardUnits;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\DTOs\StockMovementSummary;
use Modules\Inventory\Enums\AdjustmentReason;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Enums\TransferStatus;
use Modules\Inventory\Events\StockBelowReorderLevel;
use Modules\Inventory\Exceptions\InsufficientStock;
use Modules\Inventory\Exceptions\StockRuleViolated;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\StockTransfer;
use Modules\Inventory\Models\Store;
use Modules\Inventory\Models\Unit;
use Modules\Property\Models\Property;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(SeedStandardUnits::class)->handle());
});

function stStore(string $code = 'MAIN', string $kind = 'main', ?int $property = null): Store
{
    return booking(fn (): Store => app(SaveStore::class)->handle($property ?? bookingIds()['property'], null, ['code' => $code, 'name' => ucfirst(strtolower($code)).' store', 'kind' => $kind]));
}

/**
 * @param  array<string, mixed>  $overrides
 * @param  list<array{unit_id: int, factor: string}>  $conversions
 */
function stItem(string $name = 'Basmati rice', array $overrides = [], array $conversions = []): Item
{
    return booking(function () use ($name, $overrides, $conversions): Item {
        $category = ItemCategory::query()->firstOrCreate(['name' => 'Food'], ['sort_order' => 0, 'is_active' => true]);

        return app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => $name, 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id'), ...$overrides], $conversions);
    });
}

/**
 * @param  array<string, mixed>  $extra  named NewStockMovement arguments
 */
function stMove(MovementType $type, Store $store, Item $item, string $quantity, ?string $cost = null, array $extra = []): StockMovementSummary
{
    return booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement($type, $store->id, $item->id, $quantity, $cost, ...$extra)));
}

/** @return array{string, string} quantity and average cost on hand */
function stOnHand(Store $store, Item $item): array
{
    $balance = booking(fn () => app(StockMovementContract::class)->balance($store->id, $item->id));

    return [$balance->quantity, $balance->avgCost];
}

it('averages the cost across receipts at different prices and issues at the average (done when)', function (): void {
    $store = stStore();
    $rice = stItem();

    stMove(MovementType::Receipt, $store, $rice, '10', '100');
    $second = stMove(MovementType::Receipt, $store, $rice, '10', '120');
    expect(stOnHand($store, $rice))->toBe(['20.0000', '110.0000'])->and($second->quantityAfter)->toBe('20.0000')->and($second->avgCostAfter)->toBe('110.0000');

    $issue = stMove(MovementType::Issue, $store, $rice, '5');
    expect($issue->quantity)->toBe('-5.0000')->and($issue->unitCost)->toBe('110.0000')->and($issue->totalCost)->toBe('550.00')->and($issue->quantityAfter)->toBe('15.0000');

    stMove(MovementType::Receipt, $store, $rice, '5', '130');
    expect(stOnHand($store, $rice))->toBe(['20.0000', '115.0000'])
        ->and(booking(fn () => app(StockMovementContract::class)->balance($store->id, $rice->id)->value))->toBe('2300.00');
});

it('records quantities in another unit of the item, with the cost per that unit', function (): void {
    $store = stStore();
    $sack = booking(fn (): int => Unit::query()->where('code', 'sack')->value('id'));
    $rice = stItem(conversions: [['unit_id' => $sack, 'factor' => '25']]);

    $in = stMove(MovementType::Receipt, $store, $rice, '2', '3000', ['unitId' => $sack]);

    expect($in->quantity)->toBe('50.0000')->and($in->unitCost)->toBe('120.0000')->and($in->totalCost)->toBe('6000.00')->and(stOnHand($store, $rice))->toBe(['50.0000', '120.0000']);
});

it('refuses to issue more than the store holds, unless the tenant allows negative stock', function (): void {
    $store = stStore();
    $rice = stItem();
    stMove(MovementType::Receipt, $store, $rice, '5', '100');

    expect(fn () => stMove(MovementType::Issue, $store, $rice, '8'))->toThrow(InsufficientStock::class, 'holds only 5');
    expect(stOnHand($store, $rice))->toBe(['5.0000', '100.0000'])->and(booking(fn (): int => StockMovement::query()->count()))->toBe(1);

    booking(fn () => app(Settings::class)->set('inventory.allow_negative_stock', true));
    $negative = stMove(MovementType::Issue, $store, $rice, '8');

    expect($negative->quantityAfter)->toBe('-3.0000')->and(stOnHand($store, $rice))->toBe(['-3.0000', '100.0000']);

    stMove(MovementType::Receipt, $store, $rice, '10', '120');
    expect(stOnHand($store, $rice))->toBe(['7.0000', '120.0000']);
});

it('returns the earlier movement for a repeated idempotency key', function (): void {
    $store = stStore();
    $rice = stItem();
    stMove(MovementType::Receipt, $store, $rice, '10', '100');

    $first = stMove(MovementType::Consumption, $store, $rice, '3', extra: ['idempotencyKey' => 'pos_bill:1:item:9', 'sourceType' => 'pos_bill', 'sourceId' => 1]);
    $again = stMove(MovementType::Consumption, $store, $rice, '3', extra: ['idempotencyKey' => 'pos_bill:1:item:9']);

    expect($again->id)->toBe($first->id)->and(stOnHand($store, $rice))->toBe(['7.0000', '100.0000'])->and(booking(fn (): int => StockMovement::query()->count()))->toBe(2);
});

it('refuses what makes no sense: not stocked, no quantity, a receipt without a cost, an unknown store', function (): void {
    $store = stStore();
    $service = stItem('Delivery', ['is_stockable' => false]);
    $rice = stItem();

    expect(fn () => stMove(MovementType::Receipt, $store, $service, '1', '5'))->toThrow(StockRuleViolated::class, 'not a stocked item')
        ->and(fn () => stMove(MovementType::Receipt, $store, $rice, '0', '5'))->toThrow(StockRuleViolated::class)
        ->and(fn () => stMove(MovementType::Receipt, $store, $rice, '5'))->toThrow(StockRuleViolated::class, 'needs the cost')
        ->and(fn () => booking(fn () => app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, 999999, $rice->id, '1', '1'))))->toThrow(StockRuleViolated::class)
        ->and(fn () => stMove(MovementType::Reversal, $store, $rice, '1', '1'))->toThrow(StockRuleViolated::class);
});

it('never changes or deletes a movement', function (): void {
    $store = stStore();
    $rice = stItem();
    stMove(MovementType::Receipt, $store, $rice, '10', '100');
    $movement = booking(fn (): StockMovement => StockMovement::query()->firstOrFail());

    expect(fn () => booking(fn () => $movement->update(['notes' => 'edited'])))->toThrow(StockRuleViolated::class)
        ->and(fn () => booking(fn () => $movement->delete()))->toThrow(StockRuleViolated::class)
        ->and(booking(fn (): ?string => StockMovement::query()->firstOrFail()->notes))->toBeNull();
});

it('records an opening balance once per item and store', function (): void {
    $store = stStore();
    $rice = stItem();
    $sugar = stItem('Sugar');

    expect(booking(fn (): int => app(RecordOpeningBalance::class)->handle($store->id, [['item_id' => $rice->id, 'quantity' => '40', 'unit_cost' => '120'], ['item_id' => $sugar->id, 'quantity' => '10', 'unit_cost' => '90']])))->toBe(2)
        ->and(stOnHand($store, $rice))->toBe(['40.0000', '120.0000']);

    expect(fn () => booking(fn () => app(RecordOpeningBalance::class)->handle($store->id, [['item_id' => $rice->id, 'quantity' => '1', 'unit_cost' => '1']])))->toThrow(StockRuleViolated::class, 'adjustment')
        ->and(fn () => booking(fn () => app(RecordOpeningBalance::class)->handle($store->id, [])))->toThrow(StockRuleViolated::class)
        ->and(fn () => booking(fn () => app(RecordOpeningBalance::class)->handle(stStore('BAR', 'bar')->id, [['item_id' => $rice->id, 'quantity' => '1', 'unit_cost' => '1'], ['item_id' => $rice->id, 'quantity' => '2', 'unit_cost' => '1']])))->toThrow(StockRuleViolated::class, 'twice');
});

it('adjusts stock found and lost with a reason, found stock at the standard cost when none is given', function (): void {
    $store = stStore();
    $rice = stItem(overrides: ['standard_cost' => '125']);
    stMove(MovementType::Receipt, $store, $rice, '10', '100');
    $adjust = fn (bool $gain, string $quantity, AdjustmentReason $reason, ?string $notes = null, ?string $cost = null) => booking(fn () => app(AdjustStock::class)->handle($store->id, $rice->id, $gain, $quantity, $reason, $notes, $cost));

    $loss = $adjust(false, '2', AdjustmentReason::Damage);
    expect($loss->type)->toBe(MovementType::AdjustmentOut)->and($loss->unitCost)->toBe('100.0000')->and(stOnHand($store, $rice))->toBe(['8.0000', '100.0000']);

    $found = $adjust(true, '2', AdjustmentReason::CountVariance, cost: '110');
    expect($found->unitCost)->toBe('110.0000')->and(stOnHand($store, $rice))->toBe(['10.0000', '102.0000']);

    expect($adjust(true, '2', AdjustmentReason::CountVariance)->unitCost)->toBe('102.0000');
    expect(fn () => $adjust(false, '1', AdjustmentReason::Other))->toThrow(StockRuleViolated::class, 'other');

    $empty = stStore('BAR', 'bar');
    expect(booking(fn () => app(AdjustStock::class)->handle($empty->id, $rice->id, true, '4', AdjustmentReason::CountVariance))->unitCost)->toBe('125.0000');
});

it('reverses a movement at its own cost, once', function (): void {
    $store = stStore();
    $rice = stItem();
    stMove(MovementType::Receipt, $store, $rice, '10', '100');
    stMove(MovementType::Receipt, $store, $rice, '10', '120');
    $second = booking(fn (): StockMovement => StockMovement::query()->orderByDesc('id')->firstOrFail());

    $reversal = booking(fn () => app(ReverseMovement::class)->handle($second, 'Entered twice'));

    expect($reversal->type)->toBe(MovementType::Reversal)->and($reversal->quantity)->toBe('-10.0000')->and(stOnHand($store, $rice))->toBe(['10.0000', '100.0000']);
    expect(fn () => booking(fn () => app(ReverseMovement::class)->handle($second->fresh(), 'Again')))->toThrow(StockRuleViolated::class)
        ->and(fn () => booking(fn () => app(ReverseMovement::class)->handle(StockMovement::query()->orderByDesc('id')->firstOrFail(), 'Reversal of a reversal')))->toThrow(StockRuleViolated::class)
        ->and(fn () => booking(fn () => app(ReverseMovement::class)->handle(StockMovement::query()->orderBy('id')->firstOrFail(), ' ')))->toThrow(StockRuleViolated::class, 'reason');

    // Reversing an issue puts the stock back at the cost it left at.
    $issue = stMove(MovementType::Issue, $store, $rice, '4');
    booking(fn () => app(ReverseMovement::class)->handle(StockMovement::query()->findOrFail($issue->id), 'Wrong store'));
    expect(stOnHand($store, $rice))->toBe(['10.0000', '100.0000']);
});

it('sends stock in two steps: out of the source at its average cost, into the destination at that cost', function (): void {
    $main = stStore('MAIN');
    $kitchen = stStore('KITCHEN', 'kitchen');
    $rice = stItem();
    stMove(MovementType::Receipt, $main, $rice, '100', '110');
    stMove(MovementType::Receipt, $kitchen, $rice, '20', '140');

    $transfer = booking(fn (): StockTransfer => app(DispatchTransfer::class)->handle($main->id, $kitchen->id, [['item_id' => $rice->id, 'quantity' => '30']], 'Weekly supply'));

    expect($transfer->transfer_no)->toStartWith('TRF-')->and($transfer->status)->toBe(TransferStatus::Dispatched)->and($transfer->lines->sole()->unit_cost)->toBe('110.0000')
        ->and(stOnHand($main, $rice))->toBe(['70.0000', '110.0000'])->and(stOnHand($kitchen, $rice))->toBe(['20.0000', '140.0000']);

    $received = booking(fn (): StockTransfer => app(ReceiveTransfer::class)->handle($transfer));
    expect($received->status)->toBe(TransferStatus::Received)->and(stOnHand($kitchen, $rice))->toBe(['50.0000', '122.0000']);
    expect(fn () => booking(fn () => app(ReceiveTransfer::class)->handle($transfer)))->toThrow(StockRuleViolated::class, 'received')
        ->and(fn () => booking(fn () => app(CancelTransfer::class)->handle($transfer)))->toThrow(StockRuleViolated::class);
});

it('calls a transfer in transit back to the source at its dispatch cost', function (): void {
    $main = stStore('MAIN');
    $kitchen = stStore('KITCHEN', 'kitchen');
    $rice = stItem();
    stMove(MovementType::Receipt, $main, $rice, '100', '110');
    $transfer = booking(fn (): StockTransfer => app(DispatchTransfer::class)->handle($main->id, $kitchen->id, [['item_id' => $rice->id, 'quantity' => '40']]));

    booking(fn () => app(CancelTransfer::class)->handle($transfer));

    expect(stOnHand($main, $rice))->toBe(['100.0000', '110.0000'])->and(stOnHand($kitchen, $rice))->toBe(['0.0000', '0.0000'])
        ->and(booking(fn (): TransferStatus => $transfer->fresh()->status))->toBe(TransferStatus::Cancelled);
    expect(fn () => booking(fn () => app(ReceiveTransfer::class)->handle($transfer)))->toThrow(StockRuleViolated::class);
});

it('dispatches all lines or none, and only between two stores of one property', function (): void {
    $main = stStore('MAIN');
    $kitchen = stStore('KITCHEN', 'kitchen');
    $other = stStore('DHK', 'main', booking(fn (): int => Property::factory()->create(['code' => 'DHK'])->id));
    $rice = stItem();
    $sugar = stItem('Sugar');
    stMove(MovementType::Receipt, $main, $rice, '10', '100');
    stMove(MovementType::Receipt, $main, $sugar, '1', '90');
    $send = fn (array $lines, ?Store $to = null) => booking(fn () => app(DispatchTransfer::class)->handle($main->id, ($to ?? $kitchen)->id, $lines));

    expect(fn () => $send([['item_id' => $rice->id, 'quantity' => '5'], ['item_id' => $sugar->id, 'quantity' => '2']]))->toThrow(InsufficientStock::class);
    expect(stOnHand($main, $rice))->toBe(['10.0000', '100.0000'])->and(booking(fn (): int => StockTransfer::query()->count()))->toBe(0);

    expect(fn () => $send([['item_id' => $rice->id, 'quantity' => '5']], $other))->toThrow(StockRuleViolated::class, 'one property')
        ->and(fn () => $send([['item_id' => $rice->id, 'quantity' => '5']], $main))->toThrow(StockRuleViolated::class, 'different stores')
        ->and(fn () => $send([]))->toThrow(StockRuleViolated::class)
        ->and(fn () => $send([['item_id' => $rice->id, 'quantity' => '1'], ['item_id' => $rice->id, 'quantity' => '2']]))->toThrow(StockRuleViolated::class, 'once per transfer')
        ->and(fn () => $send([['item_id' => $rice->id, 'quantity' => '0']]))->toThrow(StockRuleViolated::class);
});

it('warns once when stock falls to its reorder level, using the store\'s level before the item\'s', function (): void {
    Event::fake([StockBelowReorderLevel::class]);
    $store = stStore();
    $rice = stItem(overrides: ['reorder_level' => '10']);
    stMove(MovementType::Receipt, $store, $rice, '30', '100');

    stMove(MovementType::Issue, $store, $rice, '15');
    Event::assertNotDispatched(StockBelowReorderLevel::class);

    stMove(MovementType::Issue, $store, $rice, '6');
    Event::assertDispatchedTimes(StockBelowReorderLevel::class, 1);
    Event::assertDispatched(StockBelowReorderLevel::class, fn (StockBelowReorderLevel $event): bool => $event->quantity === '9.0000' && $event->reorderLevel === '10.0000' && $event->itemId === $rice->id && $event->storeId === $store->id);

    stMove(MovementType::Issue, $store, $rice, '2');
    Event::assertDispatchedTimes(StockBelowReorderLevel::class, 1);

    booking(fn () => app(SaveItem::class)->handle($rice, [...$rice->only(['item_category_id', 'sku', 'name', 'base_unit_id']), 'reorder_level' => '10'], [], [['store_id' => $store->id, 'par_level' => null, 'reorder_level' => '20']]));
    stMove(MovementType::Receipt, $store, $rice, '30', '100');
    stMove(MovementType::Issue, $store, $rice, '20');
    Event::assertDispatchedTimes(StockBelowReorderLevel::class, 2);
});
