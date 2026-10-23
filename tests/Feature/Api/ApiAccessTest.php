<?php

/*
| The REST API's access rules (Step 8.1): bearer tokens on the tenant's own subdomain, the plan's API access, the token's abilities
| and properties, the module switch, the rate limit and the one error shape.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Modules\IAM\Enums\UserStatus;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;
use Modules\Platform\Models\Plan;
use Modules\Platform\Services\TenantPlanLimits;
use Modules\Property\Models\Property;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    foreach (['sunrise', 'moonlight'] as $slug) {
        $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => $slug]));
        app(TenantContext::class)->run($tenant, function () use ($tenant): void {
            $first = Property::factory()->create(['code' => 'ONE', 'name' => 'First']);
            $second = Property::factory()->create(['code' => 'TWO', 'name' => 'Second']);
            $agent = tenantUserAs($tenant, DefaultRole::ReservationAgent, ['email' => 'agent@example.com']);
            DB::table('property_user')->insert([
                ['tenant_id' => $tenant->id, 'user_id' => $agent->id, 'property_id' => $first->id],
                ['tenant_id' => $tenant->id, 'user_id' => $agent->id, 'property_id' => $second->id],
            ]);
        });
    }
});

/** @return array{0: User, 1: list<int>} the agent and the ids of First and Second */
function apAgent(string $slug = 'sunrise'): array
{
    $user = userIn($slug, 'agent@example.com');
    $ids = app(TenantContext::class)->run(tenant($slug), fn (): array => Property::query()->orderBy('code')->pluck('id')->all());

    return [$user, $ids];
}

it('answers who the token is and where it may work', function (): void {
    [$agent, [$first, $second]] = apAgent();

    api(apiToken($agent, ['reservation.booking.view']))->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))
        ->assertOk()
        ->assertJsonPath('data.user.email', 'agent@example.com')
        ->assertJsonPath('data.token.abilities', ['reservation.booking.view'])
        ->assertJsonCount(2, 'data.properties');

    api(apiToken($agent, ['reservation.booking.view'], [$second]))->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))
        ->assertOk()->assertJsonPath('data.properties', [['id' => $second, 'name' => 'Second']]);
});

it('needs a valid token, from its own tenant', function (): void {
    [$agent] = apAgent();
    [$other] = apAgent('moonlight');
    $url = tenantUrl('sunrise', '/api/v1/iam/me');

    api('')->getJson($url)->assertUnauthorized()->assertExactJson(['message' => 'A valid API token is required.', 'code' => 'unauthenticated']);
    api('not-a-token')->getJson($url)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    api(apiToken($other))->getJson($url)->assertUnauthorized();
    api(apiToken($agent, expiresAt: now()->subMinute()))->getJson($url)->assertUnauthorized();
});

it('stops a revoked token and the tokens of a deactivated user', function (): void {
    [$agent] = apAgent();
    $token = apiToken($agent);
    $url = tenantUrl('sunrise', '/api/v1/iam/me');
    api($token)->getJson($url)->assertOk();

    app(TenantContext::class)->run(tenant('sunrise'), fn () => ApiToken::query()->delete());
    api($token)->getJson($url)->assertUnauthorized();

    $token = apiToken($agent);
    app(TenantContext::class)->run(tenant('sunrise'), fn () => $agent->forceFill(['status' => UserStatus::Inactive])->save());
    api($token)->getJson($url)->assertUnauthorized();
});

it('refuses a tenant whose plan has no API access', function (): void {
    [$agent] = apAgent();
    $plan = Plan::factory()->create(['code' => 'basic', 'api_access' => false]);
    tenant('sunrise')->forceFill(['plan_id' => $plan->id])->save();

    api(apiToken($agent))->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))
        ->assertForbidden()->assertJsonPath('code', 'api_not_in_plan');

    $plan->forceFill(['api_access' => true])->save();
    app(TenantPlanLimits::class)->flush();
    api(apiToken($agent))->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))->assertOk();
});

it('needs both the permission and the ability for each endpoint', function (): void {
    [$agent, [$first]] = apAgent();
    $url = tenantUrl('sunrise', '/api/v1/reservation/reservations?property_id='.$first);

    api(apiToken($agent, ['guest.guest.view']))->getJson($url)
        ->assertForbidden()->assertJsonPath('code', 'forbidden')->assertJsonPath('message', 'This token was not given the reservation.booking.view ability.');

    // The token was given it, but the user no longer holds it (their role changed).
    $token = apiToken($agent, ['reservation.booking.view']);
    app(TenantContext::class)->run(tenant('sunrise'), fn () => $agent->syncRoles([DefaultRole::Chef->value]));
    api($token)->getJson($url)->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('keeps a token to its properties', function (): void {
    [$agent, [$first, $second]] = apAgent();
    $token = apiToken($agent, ['reservation.booking.view'], [$first]);

    api($token)->getJson(tenantUrl('sunrise', '/api/v1/reservation/reservations?property_id='.$first))->assertOk();
    api($token)->getJson(tenantUrl('sunrise', '/api/v1/reservation/reservations?property_id='.$second))
        ->assertForbidden()->assertExactJson(['message' => "This token may not work in property {$second}.", 'code' => 'property_denied']);
});

it('refuses a module the tenant does not have', function (): void {
    [$agent] = apAgent();
    DB::table('tenant_modules')->insert(['tenant_id' => tenant('sunrise')->id, 'module' => 'reservation', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);

    api(apiToken($agent))->getJson(tenantUrl('sunrise', '/api/v1/reservation/reservations'))->assertForbidden()->assertJsonPath('code', 'forbidden');
});

it('answers unknown addresses and records when the token was used', function (): void {
    [$agent] = apAgent();
    $token = apiToken($agent);

    api($token)->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))->assertOk();
    api($token)->getJson(tenantUrl('sunrise', '/api/v1/nothing-here'))->assertNotFound()->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);
    api($token)->getJson(tenantUrl('nowhere', '/api/v1/iam/me'))->assertNotFound()->assertJsonPath('code', 'not_found');

    $used = app(TenantContext::class)->run(tenant('sunrise'), fn (): ?ApiToken => ApiToken::query()->first());
    expect($used?->last_used_at)->not->toBeNull()->and($used?->last_used_ip)->toBe('127.0.0.1');
});

it('limits each token to 120 requests a minute', function (): void {
    [$agent] = apAgent();
    $token = apiToken($agent);

    foreach (range(1, 120) as $ignored) {
        api($token)->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))->assertOk();
    }

    api($token)->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))
        ->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests')->assertHeader('Retry-After');
    api(apiToken($agent))->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))->assertOk();
});
