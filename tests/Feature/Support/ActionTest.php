<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\StoreCacheValues;

uses(RefreshDatabase::class);

it('resolves the action from the container', function (): void {
    expect(StoreCacheValues::make())->toBeInstanceOf(StoreCacheValues::class);
});

it('commits all writes of a successful transaction', function (): void {
    expect(StoreCacheValues::make()->handle())->toBe(2)
        ->and(DB::table('cache')->count())->toBe(2);
});

it('rolls back every write when the transaction fails', function (): void {
    expect(fn (): int => StoreCacheValues::make()->handle(failHalfway: true))
        ->toThrow(RuntimeException::class, 'Failed halfway');

    expect(DB::table('cache')->count())->toBe(0);
});
