<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Tenancy\IsolationProbe;
use Tests\Fixtures\Tenancy\PlainJob;
use Tests\Fixtures\Tenancy\RecordTenantJob;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['queue.default' => 'database']);
});

/**
 * Simulate a fresh worker process: no tenant, no context. Then process one job.
 */
function runQueuedJobInFreshWorker(): void
{
    app(TenantContext::class)->forget();
    Context::flush();

    Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--stop-when-empty' => true]);
}

it('restores the dispatching tenant in a queued job', function (): void {
    [$a, $b] = [Tenant::factory()->create(['slug' => 'tenant-a']), Tenant::factory()->create(['slug' => 'tenant-b'])];
    $context = app(TenantContext::class);
    $context->run($a, fn () => IsolationProbe::factory()->create(['code' => 'A-1']));
    $context->run($b, fn () => IsolationProbe::factory()->create(['code' => 'B-1']));

    // Dispatch as a statement: a returned PendingDispatch would only queue the job after run() restores the context.
    $context->run($b, function (): void {
        RecordTenantJob::dispatch();
    });
    expect(DB::table('jobs')->count())->toBe(1);

    runQueuedJobInFreshWorker();

    expect(DB::table('failed_jobs')->value('exception'))->toBeNull()
        ->and(DB::table('jobs')->count())->toBe(0)
        ->and(Cache::get('job.tenant_id'))->toBe($b->id)
        ->and(Cache::get('job.probe_codes'))->toBe(['B-1'])
        ->and(app(TenantContext::class)->check())->toBeFalse();
});

it('fails a tenant-aware job that was dispatched without a tenant', function (): void {
    RecordTenantJob::dispatch();

    runQueuedJobInFreshWorker();

    expect(Cache::has('job.tenant_id'))->toBeFalse()
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and((string) DB::table('failed_jobs')->value('exception'))->toContain('dispatched without a current tenant');
});

it('gives jobs that are not tenant-aware no tenant', function (): void {
    $tenant = Tenant::factory()->create();

    app(TenantContext::class)->run($tenant, function (): void {
        PlainJob::dispatch();
    });
    runQueuedJobInFreshWorker();

    expect(Cache::get('plain.has_tenant'))->toBeFalse();
});
