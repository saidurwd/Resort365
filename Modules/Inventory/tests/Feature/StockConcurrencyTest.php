<?php

/*
| Concurrent stock issues (Step 5.3). "Done when": concurrent issues never produce a wrong balance. Separate PHP
| processes issue from one balance at the same time; the stock held, the movements written and the balance agree.
*/
use App\Actions\Tenancy\CreateTenant;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;
use Modules\Inventory\Actions\SaveItem;
use Modules\Inventory\Actions\SaveStore;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Models\ItemCategory;
use Modules\Inventory\Models\StockBalance;
use Modules\Inventory\Models\StockMovement;
use Modules\Inventory\Models\Unit;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(DatabaseTruncation::class);

/**
 * @param  list<string>  $quantities  one issue per process
 * @return list<string>
 */
function issueInParallel(int $storeId, int $itemId, array $quantities): array
{
    $tenant = tenant('race');
    $connection = config('database.connections.mysql');
    $env = [
        'APP_ENV' => 'testing', 'CACHE_STORE' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'], 'DB_PASSWORD' => (string) $connection['password'],
    ];

    $results = Process::pool(function (Pool $pool) use ($quantities, $tenant, $env, $storeId, $itemId): void {
        foreach ($quantities as $i => $quantity) {
            $pool->as((string) $i)->env($env)->timeout(120)->command([PHP_BINARY, __DIR__.'/../Support/issue.php', (string) $tenant->id, (string) $storeId, (string) $itemId, $quantity]);
        }
    })->start()->wait();

    return array_values(array_map(fn ($result): string => trim($result->output()).($result->errorOutput() !== '' ? ' ERR '.mb_substr($result->errorOutput(), 0, 300) : ''), $results->collect()->all()));
}

/**
 * @return array{int, int} store and item ids, with 100 kg received at 50
 */
function raceStock(string $received = '100'): array
{
    return booking(function () use ($received): array {
        $store = app(SaveStore::class)->handle(bookingIds('race')['property'], null, ['code' => 'MAIN', 'name' => 'Main store', 'kind' => 'main']);
        $category = ItemCategory::query()->create(['name' => 'Food', 'sort_order' => 0, 'is_active' => true]);
        $item = app(SaveItem::class)->handle(null, ['item_category_id' => $category->id, 'name' => 'Rice', 'base_unit_id' => Unit::query()->where('code', 'kg')->value('id')]);
        app(StockMovementContract::class)->record(new NewStockMovement(MovementType::Receipt, $store->id, $item->id, $received, '50'));

        return [$store->id, $item->id];
    }, 'race');
}

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(CreateTenant::make()->handle('race', 'Race Resort')));
});

it('keeps the balance exact when many issues run at once', function (): void {
    [$storeId, $itemId] = raceStock('100');

    $outputs = issueInParallel($storeId, $itemId, array_fill(0, 10, '7'));

    $balance = booking(fn () => StockBalance::query()->where('store_id', $storeId)->where('item_id', $itemId)->firstOrFail(), 'race');
    $issued = booking(fn (): string => (string) StockMovement::query()->where('movement_type', 'issue')->sum('quantity'), 'race');

    expect(collect($outputs)->filter(fn (string $out): bool => str_starts_with($out, 'ISSUED'))->count())->toBe(10, implode(' | ', $outputs))
        ->and($balance->quantity)->toBe('30.0000')->and($balance->avg_cost)->toBe('50.0000')->and(rtrim(rtrim($issued, '0'), '.'))->toBe('-70')
        ->and(booking(fn (): array => StockMovement::query()->where('movement_type', 'issue')->orderBy('id')->pluck('quantity_after')->map(fn ($after): string => (string) $after)->all(), 'race'))
        ->toBe(['93.0000', '86.0000', '79.0000', '72.0000', '65.0000', '58.0000', '51.0000', '44.0000', '37.0000', '30.0000']);
});

it('lets exactly the issues the stock can cover go through when it is short', function (): void {
    [$storeId, $itemId] = raceStock('20');

    $outputs = issueInParallel($storeId, $itemId, array_fill(0, 8, '5'));

    $balance = booking(fn () => StockBalance::query()->where('store_id', $storeId)->where('item_id', $itemId)->firstOrFail(), 'race');

    expect(collect($outputs)->filter(fn (string $out): bool => str_starts_with($out, 'ISSUED'))->count())->toBe(4, implode(' | ', $outputs))
        ->and(collect($outputs)->filter(fn (string $out): bool => $out === 'SHORT')->count())->toBe(4, implode(' | ', $outputs))
        ->and($balance->quantity)->toBe('0.0000')
        ->and(booking(fn (): int => StockMovement::query()->where('movement_type', 'issue')->count(), 'race'))->toBe(4);
});
