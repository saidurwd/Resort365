<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantContextMissing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;

uses(RefreshDatabase::class);

it('starts without a tenant', function (): void {
    $context = app(TenantContext::class);

    expect($context->check())->toBeFalse()
        ->and($context->id())->toBeNull()
        ->and(fn () => $context->tenantOrFail())->toThrow(TenantContextMissing::class);
});

it('records the tenant in hidden context so it travels with queued jobs', function (): void {
    $tenant = Tenant::factory()->create(['slug' => 'sunrise']);

    app(TenantContext::class)->set($tenant);

    expect(Context::getHidden(TenantContext::CONTEXT_KEY))->toBe($tenant->id)
        ->and(Context::get('tenant'))->toBe('sunrise');

    app(TenantContext::class)->forget();

    expect(Context::getHidden(TenantContext::CONTEXT_KEY))->toBeNull();
});

it('runs a callback as a tenant and restores the previous one', function (): void {
    [$a, $b] = Tenant::factory()->count(2)->create()->all();
    $context = app(TenantContext::class);
    $context->set($a);

    $seen = $context->run($b, fn (Tenant $tenant): int => $context->id() + 0 * $tenant->id);

    expect($seen)->toBe($b->id)
        ->and($context->id())->toBe($a->id);
});

it('restores the previous tenant even when the callback throws', function (): void {
    $tenant = Tenant::factory()->create();
    $context = app(TenantContext::class);

    expect(fn () => $context->run($tenant, fn () => throw new RuntimeException('boom')))->toThrow(RuntimeException::class)
        ->and($context->check())->toBeFalse();
});
