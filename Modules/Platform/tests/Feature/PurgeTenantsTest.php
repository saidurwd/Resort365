<?php

/*
| Purging a cancelled tenant after its retention period (Step 7.5): every row with its tenant_id and its files go, the platform's own
| records stay, other tenants are untouched, and a dry run deletes nothing.
*/
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Guest\Models\Guest;
use Modules\Platform\Actions\PurgeTenantData;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\PlatformInvoice;

use function Pest\Laravel\artisan;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

require_once __DIR__.'/../Support/platform-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('attachments');
    pfSetup();
    withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));
    app(TenantContext::class)->run(tenant('other'), fn () => Guest::factory()->create(['first_name' => 'Stays']));
    Storage::disk('attachments')->put('tenants/'.tenant('sunrise')->id.'/media/1/passport.pdf', 'scan');
    Storage::disk('attachments')->put('tenants/'.tenant('other')->id.'/media/2/passport.pdf', 'scan');
    PlatformInvoice::factory()->create(['tenant_id' => tenant('sunrise')->id]);
    tenant('sunrise')->forceFill(['status' => TenantStatus::Cancelled, 'cancelled_at' => CarbonImmutable::parse('2026-09-01')])->save();
});

afterEach(fn () => travelBack());

it('refuses before the retention period is over, and lists what a dry run would delete', function (): void {
    travelTo(CarbonImmutable::parse('2026-09-20'));
    expect(fn () => app(PurgeTenantData::class)->handle(tenant('sunrise')))->toThrow(PlatformRuleViolated::class, '30 days');

    travelTo(CarbonImmutable::parse('2026-10-05'));
    $counts = app(PurgeTenantData::class)->handle(tenant('sunrise'), dryRun: true);

    expect($counts['guests'] ?? 0)->toBeGreaterThan(0)->and($counts)->not->toHaveKey('platform_invoices')
        ->and(DB::table('guests')->where('tenant_id', tenant('sunrise')->id)->count())->toBe($counts['guests'])
        ->and(tenant('sunrise')->purged_at)->toBeNull();
});

it('deletes every row and file of the tenant and nothing of anyone else\'s', function (): void {
    travelTo(CarbonImmutable::parse('2026-10-05'));
    artisan('platform:purge-tenants')->assertSuccessful();

    $left = collect(PurgeTenantData::tables())->filter(fn (string $table): bool => DB::table($table)->where('tenant_id', tenant('sunrise')->id)->exists())->values()->all();

    expect($left)->toBe([])
        ->and(tenant('sunrise')->purged_at)->not->toBeNull()
        ->and(PlatformInvoice::query()->where('tenant_id', tenant('sunrise')->id)->count())->toBe(1)
        ->and(Storage::disk('attachments')->exists('tenants/'.tenant('sunrise')->id.'/media/1/passport.pdf'))->toBeFalse()
        ->and(Storage::disk('attachments')->exists('tenants/'.tenant('other')->id.'/media/2/passport.pdf'))->toBeTrue()
        ->and(app(TenantContext::class)->run(tenant('other'), fn (): int => Guest::query()->count()))->toBe(1)
        ->and(pfAudit('tenant.purged'))->toBe(['tenant.purged']);

    expect(fn () => app(PurgeTenantData::class)->handle(tenant('sunrise')))->toThrow(PlatformRuleViolated::class, 'already purged');
    artisan('platform:purge-tenants')->expectsOutputToContain('No tenant is due')->assertSuccessful();
});
