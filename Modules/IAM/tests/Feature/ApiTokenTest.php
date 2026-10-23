<?php

/*
| Setup → API tokens (Step 8.1): a token for yourself with some of your permissions, shown once; your own list or everyone's with
| iam.api-token.manage; revoking; and no tokens on a plan without API access.
*/

use App\Http\Middleware\SetCurrentProperty;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\IAM\Actions\CreateApiToken;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;
use Modules\Platform\Models\Plan;
use Modules\Property\Models\Property;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withSession;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $tenant = withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    app(TenantContext::class)->run($tenant, function () use ($tenant): void {
        $first = Property::factory()->create(['code' => 'ONE', 'name' => 'First']);
        Property::factory()->create(['code' => 'TWO', 'name' => 'Second']);
        $manager = tenantUserAs($tenant, DefaultRole::FrontOfficeManager, ['email' => 'fom@sunrise.test']);
        tenantUserAs($tenant, DefaultRole::TenantOwner, ['email' => 'owner@sunrise.test']);
        tenantUserAs($tenant, DefaultRole::FrontDeskAgent, ['email' => 'desk@sunrise.test']);
        DB::table('property_user')->insert(['tenant_id' => $tenant->id, 'user_id' => $manager->id, 'property_id' => $first->id]);
    });
});

function atSignIn(string $email): User
{
    $user = userIn('sunrise', $email);
    withSession([SetCurrentProperty::SESSION_KEY => null]);
    actingAs($user);

    return $user;
}

/** @return list<ApiToken> */
function atTokens(): array
{
    return app(TenantContext::class)->run(tenant('sunrise'), fn (): array => ApiToken::query()->with('tokenable')->orderBy('id')->get()->all());
}

it('creates a token with some of your permissions and shows it once', function (): void {
    $manager = atSignIn('fom@sunrise.test');
    $first = app(TenantContext::class)->run(tenant('sunrise'), fn (): int => (int) Property::query()->where('code', 'ONE')->value('id'));

    get(tenantUrl('sunrise', '/iam/api-tokens'))->assertOk()
        ->assertSee(__('No API tokens yet'))->assertSeeHtml('data-new-token-form')->assertSeeHtml('value="reservation.booking.create"')
        ->assertDontSeeHtml('value="iam.role.delete"');

    $response = post(tenantUrl('sunrise', '/iam/api-tokens'), [
        'name' => 'Booking website', 'abilities' => ['reservation.availability.view', 'reservation.booking.create'],
        'property_ids' => [$first], 'expires_at' => now()->addMonth()->toDateString(),
    ])->assertRedirect(tenantUrl('sunrise', '/iam/api-tokens'));

    $plain = (string) session('api_token_plain');
    expect($plain)->toMatch('/^\d+\|r365_/');

    [$token] = atTokens();
    expect($token->name)->toBe('Booking website')
        ->and($token->abilities)->toBe(['reservation.availability.view', 'reservation.booking.create'])
        ->and($token->property_ids)->toBe([$first])
        ->and($token->created_by)->toBe($manager->id)
        ->and($token->token)->toBe(hash('sha256', explode('|', $plain)[1]))
        ->and($token->expires_at?->isFuture())->toBeTrue();

    $response->assertSessionHas('success');
    get(tenantUrl('sunrise', '/iam/api-tokens'))->assertSeeHtml('data-token="Booking website"')->assertSee('reservation.booking.create');

    // The plain token is a working credential for the API.
    api($plain)->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))->assertOk()->assertJsonPath('data.properties', [['id' => $first, 'name' => 'First']]);
});

it('refuses abilities and properties beyond the user and a missing choice', function (): void {
    atSignIn('fom@sunrise.test');
    $second = app(TenantContext::class)->run(tenant('sunrise'), fn (): int => (int) Property::query()->where('code', 'TWO')->value('id'));

    post(tenantUrl('sunrise', '/iam/api-tokens'), ['name' => 'X', 'abilities' => ['iam.role.delete']])->assertSessionHasErrors('abilities');
    post(tenantUrl('sunrise', '/iam/api-tokens'), ['name' => 'X', 'abilities' => ['made.up']])->assertSessionHasErrors('abilities.0');
    post(tenantUrl('sunrise', '/iam/api-tokens'), ['name' => 'X', 'abilities' => []])->assertSessionHasErrors('abilities');
    post(tenantUrl('sunrise', '/iam/api-tokens'), ['name' => 'X', 'abilities' => ['reservation.booking.view'], 'property_ids' => [$second]])->assertSessionHasErrors('property_ids');
    post(tenantUrl('sunrise', '/iam/api-tokens'), ['name' => '', 'abilities' => ['reservation.booking.view'], 'expires_at' => now()->subDay()->toDateString()])->assertSessionHasErrors(['name', 'expires_at']);

    expect(atTokens())->toBe([]);
});

it('lets people without the permission neither see nor create tokens', function (): void {
    atSignIn('desk@sunrise.test');

    get(tenantUrl('sunrise', '/iam/api-tokens'))->assertForbidden();
    post(tenantUrl('sunrise', '/iam/api-tokens'), ['name' => 'X', 'abilities' => ['reservation.booking.view']])->assertForbidden();
    expect(atTokens())->toBe([]);
});

it('revokes your own token, and only the manager revokes others', function (): void {
    $manager = userIn('sunrise', 'fom@sunrise.test');
    $owner = userIn('sunrise', 'owner@sunrise.test');
    $managerToken = apiToken($manager, ['reservation.booking.view']);
    apiToken($owner, ['reservation.booking.view']);
    [$mine, $owners] = atTokens();

    atSignIn('fom@sunrise.test');
    get(tenantUrl('sunrise', '/iam/api-tokens'))->assertOk()->assertSee('Test client')->assertDontSee($owner->name);
    delete(tenantUrl('sunrise', '/iam/api-tokens/'.$owners->id))->assertForbidden();
    delete(tenantUrl('sunrise', '/iam/api-tokens/'.$mine->id))->assertRedirect(tenantUrl('sunrise', '/iam/api-tokens'));
    expect(atTokens())->toHaveCount(1);
    api($managerToken)->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))->assertUnauthorized();

    atSignIn('owner@sunrise.test');
    $manager2 = apiToken($manager, ['reservation.booking.view']);
    get(tenantUrl('sunrise', '/iam/api-tokens'))->assertOk()->assertSee($manager->name);
    $managers = collect(atTokens())->first(fn (ApiToken $token): bool => $token->tokenable_id === $manager->id);
    delete(tenantUrl('sunrise', '/iam/api-tokens/'.$managers->id))->assertRedirect();
    api($manager2)->getJson(tenantUrl('sunrise', '/api/v1/iam/me'))->assertUnauthorized();
});

it('makes no tokens on a plan without API access', function (): void {
    $plan = Plan::factory()->create(['code' => 'basic', 'api_access' => false]);
    tenant('sunrise')->forceFill(['plan_id' => $plan->id])->save();
    $manager = atSignIn('fom@sunrise.test');

    get(tenantUrl('sunrise', '/iam/api-tokens'))->assertOk()->assertSeeHtml('data-api-not-in-plan')->assertDontSeeHtml('data-new-token-form');
    post(tenantUrl('sunrise', '/iam/api-tokens'), ['name' => 'X', 'abilities' => ['reservation.booking.view']])->assertSessionHasErrors('name');

    expect(fn () => app(TenantContext::class)->run(tenant('sunrise'), fn () => CreateApiToken::make()->handle($manager, 'X', ['reservation.booking.view'])))
        ->toThrow(ValidationException::class);
});

it('shows the API tokens menu item only with the permission', function (): void {
    atSignIn('fom@sunrise.test');
    get(tenantUrl('sunrise', '/dashboard'))->assertSee(__('API tokens'));

    atSignIn('desk@sunrise.test');
    get(tenantUrl('sunrise', '/dashboard'))->assertDontSee(__('API tokens'));
});
