<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantCache;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextMissing;
use App\Support\Tenancy\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('prefixes cache keys with the tenant id and keeps tenants apart', function (): void {
    [$a, $b] = Tenant::factory()->count(2)->create()->all();
    $context = app(TenantContext::class);
    $cache = app(TenantCache::class);

    $context->run($a, fn () => $cache->put('occupancy', 78));
    $context->run($b, fn () => $cache->put('occupancy', 41));

    expect($context->run($a, fn () => $cache->key('occupancy')))->toBe("t:{$a->id}:occupancy")
        ->and(Cache::get("t:{$a->id}:occupancy"))->toBe(78)
        ->and($context->run($b, fn () => $cache->get('occupancy')))->toBe(41)
        ->and($context->run($a, fn () => $cache->remember('rooms', 60, fn (): int => 12)))->toBe(12)
        ->and($context->run($b, fn () => $cache->has('rooms')))->toBeFalse();

    $context->run($a, fn () => $cache->forget('occupancy'));

    expect($context->run($a, fn () => $cache->has('occupancy')))->toBeFalse()
        ->and($context->run($b, fn () => $cache->has('occupancy')))->toBeTrue();
});

it('prefixes storage paths with the tenant id', function (): void {
    Storage::fake('local');
    [$a, $b] = Tenant::factory()->count(2)->create()->all();
    $context = app(TenantContext::class);
    $storage = app(TenantStorage::class);

    $context->run($a, fn () => $storage->put('invoices/INV-1.pdf', 'pdf-a'));

    Storage::disk('local')->assertExists("tenants/{$a->id}/invoices/INV-1.pdf");

    expect($context->run($a, fn () => $storage->get('invoices/INV-1.pdf')))->toBe('pdf-a')
        ->and($context->run($b, fn () => $storage->exists('invoices/INV-1.pdf')))->toBeFalse()
        ->and($context->run($a, fn () => $storage->path()))->toBe("tenants/{$a->id}");

    $context->run($a, fn () => $storage->delete('invoices/INV-1.pdf'));
    Storage::disk('local')->assertMissing("tenants/{$a->id}/invoices/INV-1.pdf");
});

it('refuses tenant cache and storage without a tenant', function (): void {
    expect(fn () => app(TenantCache::class)->key('x'))->toThrow(TenantContextMissing::class)
        ->and(fn () => app(TenantStorage::class)->path('x'))->toThrow(TenantContextMissing::class);
});
