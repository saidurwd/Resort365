<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

it('runs against MySQL', function (): void {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::scalar('select 1'))->toBe(1);
});

it('can reach Redis', function (): void {
    $key = 'resort365:test:'.bin2hex(random_bytes(4));

    Redis::connection()->setex($key, 10, 'ok');

    expect(Redis::connection()->get($key))->toBe('ok');

    Redis::connection()->del($key);
});
