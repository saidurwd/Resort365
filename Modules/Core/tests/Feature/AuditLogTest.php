<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Core\Models\Activity;
use Modules\Core\Models\ExchangeRate;
use Modules\Core\Services\AuditTrailService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\put;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(ReferenceDataSeeder::class);
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
});

/**
 * @return list<Activity>
 */
function activitiesFor(object $subject): array
{
    return app(TenantContext::class)->run(tenant('sunrise'), fn (): array => Activity::query()
        ->where('subject_type', $subject->getMorphClass())->where('subject_id', $subject->getKey())->orderBy('id')->get()->all());
}

it('records every change to a record, with old and new values and who made it', function (): void {
    $actor = tenantUserAs(tenant('sunrise'), DefaultRole::Accountant, ['name' => 'Farzana Akter']);
    actingAs($actor);

    $rate = app(TenantContext::class)->run(tenant('sunrise'), function (): ExchangeRate {
        $rate = ExchangeRate::query()->create(['base_currency' => 'USD', 'quote_currency' => 'BDT', 'rate' => '120.00000000', 'effective_date' => '2026-01-01']);
        $rate->update(['rate' => '122.50000000']);
        $rate->update(['source' => 'bank']);
        $rate->delete();

        return $rate;
    });

    $log = activitiesFor($rate);

    expect(array_map(fn (Activity $a): ?string => $a->event, $log))->toBe(['created', 'updated', 'updated', 'deleted'])
        ->and(collect($log)->every(fn (Activity $a): bool => $a->causer_id === $actor->id))->toBeTrue()
        ->and(AuditTrailService::changes($log[1]))->toBe(['rate' => ['120.00000000', '122.50000000']])
        ->and(AuditTrailService::changes($log[2]))->toBe(['source' => [null, 'bank']]);
});

it('never writes passwords or 2FA secrets to the log', function (): void {
    $user = tenantUser(tenant('sunrise'), ['name' => 'Old Name']);

    app(TenantContext::class)->run(tenant('sunrise'), function () use ($user): void {
        $user->forceFill(['name' => 'New Name', 'password' => 'Changed123', 'two_factor_secret' => encrypt('SECRET')])->save();
    });

    $json = (string) json_encode(array_map(fn (Activity $a): array => $a->toArray(), activitiesFor($user)));

    expect($json)->toContain('New Name');
    expect($json)->not->toContain('password');
    expect($json)->not->toContain('two_factor_secret');
    expect($json)->not->toContain('SECRET');
});

it('records role permission and user role changes', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    $user = tenantUser(tenant('sunrise'));

    put(tenantUrl('sunrise', '/iam/users/'.$user->id.'/roles'), ['roles' => [roleId(tenant('sunrise'), DefaultRole::Accountant)]]);

    $last = collect(activitiesFor($user))->last();
    expect($last?->description)->toBe('User roles changed')
        ->and(AuditTrailService::changes($last))->toBe(['roles' => ['', 'accountant']]);

    get(tenantUrl('sunrise', '/iam/users/'.$user->id.'/edit'))->assertOk()->assertSee('User roles changed');
});

it('shows the tenant\'s audit log to auditors only', function (): void {
    app(TenantContext::class)->run(tenant('greenvalley'), fn () => ExchangeRate::factory()->create(['source' => 'valley-only']));
    app(TenantContext::class)->run(tenant('sunrise'), fn () => ExchangeRate::factory()->create(['source' => 'sunrise-only']));

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Auditor));
    get(tenantUrl('sunrise', '/core/audit-log'))->assertOk();

    $rows = (string) json_encode(getJson(tenantUrl('sunrise', '/core/audit-log/data?draw=1&start=0&length=100'))->assertOk()->json('data'));
    expect($rows)->toContain('sunrise-only');
    expect($rows)->not->toContain('valley-only');

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/audit-log'))->assertForbidden();
});

it('shapes history for the audit trail component, newest first', function (): void {
    $rate = app(TenantContext::class)->run(tenant('sunrise'), function (): ExchangeRate {
        $rate = ExchangeRate::factory()->create(['rate' => '100.00000000']);
        $rate->update(['rate' => '101.00000000']);

        return $rate;
    });

    $trail = app(TenantContext::class)->run(tenant('sunrise'), fn (): array => app(AuditTrail::class)->for($rate));

    expect($trail)->toHaveCount(2)
        ->and($trail[0]['description'])->toBe('Exchange Rate updated')
        ->and($trail[0]['changes'])->toBe(['rate' => ['100.00000000', '101.00000000']])
        ->and($trail[1]['causer'])->toBeNull();
});
