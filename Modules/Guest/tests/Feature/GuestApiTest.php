<?php

/*
| Guest API (Step 8.1): search, show, create and update guest profiles over /api/v1/guest with a token; duplicates are a 409 until
| confirmed, and ID numbers are masked without guest.guest.view-id.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Guest\Models\Guest;
use Modules\Guest\Tests\Support\GuestSetup;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    GuestSetup::tenant();
    GuestSetup::guest(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '01711-000001', 'email' => 'rahim@example.com', 'id_type' => 'passport', 'id_number' => 'A1234567']);
    GuestSetup::guest(['first_name' => 'Ayesha', 'last_name' => 'Siddique', 'phone' => '01811-000002']);
});

/** @param  list<string>|null  $abilities */
function gaToken(DefaultRole $role = DefaultRole::FrontOfficeManager, ?array $abilities = null): string
{
    return apiToken(tenantUserAs(tenant('sunrise'), $role, ['email' => 'p'.random_int(1, 999_999).'@sunrise.test']), $abilities);
}

function gaUrl(string $path): string
{
    return tenantUrl('sunrise', '/api/v1/guest'.$path);
}

it('searches and shows guests, masking ID numbers without the ID permission', function (): void {
    $token = gaToken(abilities: ['guest.guest.view']);
    $rahim = GuestSetup::run(fn (): Guest => Guest::query()->where('first_name', 'Rahim')->sole());

    api($token)->getJson(gaUrl('/guests'))->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('meta.total', 2);
    api($token)->getJson(gaUrl('/guests?q=Rah'))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.full_name', 'Rahim Uddin');
    api($token)->getJson(gaUrl('/guests?q=01811'))->assertOk()->assertJsonPath('data.0.first_name', 'Ayesha');
    api($token)->getJson(gaUrl('/guests/'.$rahim->id))->assertOk()->assertJsonPath('data.id_number', '••••4567')->assertJsonPath('data.email', 'rahim@example.com');

    api(gaToken(abilities: ['guest.guest.view', 'guest.guest.view-id']))->getJson(gaUrl('/guests/'.$rahim->id))->assertOk()->assertJsonPath('data.id_number', 'A1234567');
    api($token)->getJson(gaUrl('/guests/999999'))->assertNotFound();
});

it('creates a guest, holding back a likely duplicate until confirmed', function (): void {
    $token = gaToken();

    api($token)->postJson(gaUrl('/guests'), ['first_name' => 'Nadia', 'last_name' => 'Karim', 'phone' => '+880 1911 000003', 'nationality_code' => 'BD'])
        ->assertCreated()->assertJsonPath('data.full_name', 'Nadia Karim')->assertJsonPath('data.vip_level', 'none');

    $duplicate = ['first_name' => 'R.', 'last_name' => 'Uddin', 'phone' => '01711-000001'];
    api($token)->postJson(gaUrl('/guests'), $duplicate)
        ->assertConflict()->assertJsonPath('code', 'possible_duplicate')->assertJsonPath('duplicates.0.name', 'Rahim Uddin');
    api($token)->postJson(gaUrl('/guests'), [...$duplicate, 'confirm_duplicate' => true])->assertCreated();

    api($token)->postJson(gaUrl('/guests'), ['last_name' => 'Nobody', 'email' => 'not-an-email'])
        ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'email']);

    expect(GuestSetup::run(fn (): int => Guest::query()->count()))->toBe(4);
});

it('updates a guest', function (): void {
    $ayesha = GuestSetup::run(fn (): Guest => Guest::query()->where('first_name', 'Ayesha')->sole());

    api(gaToken())->putJson(gaUrl('/guests/'.$ayesha->id), ['first_name' => 'Ayesha', 'last_name' => 'Rahman', 'phone' => '01811-000002', 'vip_level' => 'gold'])
        ->assertOk()->assertJsonPath('data.last_name', 'Rahman')->assertJsonPath('data.vip_level', 'gold');

    expect(GuestSetup::fresh($ayesha)->last_name)->toBe('Rahman');
});

it('needs the guest abilities', function (): void {
    $ayesha = GuestSetup::run(fn (): Guest => Guest::query()->where('first_name', 'Ayesha')->sole());
    $viewOnly = gaToken(abilities: ['guest.guest.view']);

    api($viewOnly)->postJson(gaUrl('/guests'), ['first_name' => 'X'])->assertForbidden();
    api($viewOnly)->putJson(gaUrl('/guests/'.$ayesha->id), ['first_name' => 'X'])->assertForbidden();
    api(gaToken(DefaultRole::Chef, []))->getJson(gaUrl('/guests'))->assertForbidden();
});

it('never shows another company\'s guests', function (): void {
    $moonlight = withDefaultRoles(Tenant::factory()->create(['slug' => 'moonlight']));
    $theirs = app(TenantContext::class)->run($moonlight, fn (): Guest => Guest::factory()->create(['first_name' => 'Hidden']));
    $token = gaToken();

    api($token)->getJson(gaUrl('/guests?q=Hidden'))->assertOk()->assertJsonCount(0, 'data');
    api($token)->getJson(gaUrl('/guests/'.$theirs->id))->assertNotFound();
    api($token)->putJson(gaUrl('/guests/'.$theirs->id), ['first_name' => 'Changed'])->assertNotFound();
});
