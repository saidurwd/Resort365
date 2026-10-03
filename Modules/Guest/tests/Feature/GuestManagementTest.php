<?php

/*
| Step 1.2 "Done when": creating a guest with an existing phone number warns about the
| duplicate; ID numbers are encrypted in the database.
*/

use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Activity;
use Modules\Core\Models\Media;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Models\Guest;
use Modules\Guest\Services\IdNumberHasher;
use Modules\Guest\Tests\Support\GuestSetup;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('attachments');
    GuestSetup::tenant();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function guestInput(array $overrides = []): array
{
    return [
        'title' => 'Mr', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '01711-000001', 'email' => 'Rahim.Uddin@Example.com',
        'nationality_code' => 'BD', 'id_type' => 'national_id', 'id_number' => '1990 1234 56789', 'vip_level' => 'gold',
        'preferences' => ['Non-smoking'], 'marketing_consent' => '1', 'address' => ['line1' => 'House 12, Road 5', 'city' => 'Dhaka'],
        ...$overrides,
    ];
}

it('creates a guest with a normalised phone and an encrypted ID number', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));

    get(tenantUrl('sunrise', '/guest/guests/create'))->assertOk();
    post(tenantUrl('sunrise', '/guest/guests'), guestInput())->assertSessionHasNoErrors();

    $guest = GuestSetup::run(fn (): Guest => Guest::query()->sole());
    expect($guest->phone)->toBe('+8801711000001')
        ->and($guest->email)->toBe('rahim.uddin@example.com')
        ->and($guest->id_number)->toBe('1990 1234 56789')
        ->and($guest->id_number_hash)->toBe(app(IdNumberHasher::class)->hash(IdType::NationalId, '1990123456789'))
        ->and($guest->preferences)->toBe(['Non-smoking']);

    // Encrypted at rest, and never written to the audit log.
    $raw = (string) DB::table('guests')->where('id', $guest->id)->value('id_number');
    expect($raw)->not->toContain('1990')->and(strlen($raw))->toBeGreaterThan(50);
    $log = GuestSetup::run(fn (): string => (string) json_encode(Activity::query()->where('subject_type', 'guest')->get()->toArray()));
    expect($log)->toContain('Rahim')
        ->and(str_contains($log, '1990'))->toBeFalse()
        ->and(str_contains($log, 'id_number'))->toBeFalse();
});

it('warns about a guest with the same phone, and creates it only when confirmed', function (): void {
    $existing = GuestSetup::guest(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '+880 1711 000001']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::ReservationAgent));

    post(tenantUrl('sunrise', '/guest/guests'), guestInput(['email' => 'other@example.com', 'id_type' => null, 'id_number' => null]))
        ->assertRedirect()->assertSessionHas('duplicates');
    expect(GuestSetup::run(fn (): int => Guest::query()->count()))->toBe(1);

    $form = get(tenantUrl('sunrise', '/guest/guests/create'))->assertOk();
    $form->assertSee(__('This guest may already exist'))->assertSee('Rahim Uddin')->assertSee(__('Same phone'))->assertSeeHtml(route('guest.guests.show', $existing));

    post(tenantUrl('sunrise', '/guest/guests'), guestInput(['email' => 'other@example.com', 'id_type' => null, 'id_number' => null, 'confirm_duplicate' => '1']))
        ->assertSessionHasNoErrors()->assertSessionMissing('duplicates');
    expect(GuestSetup::run(fn (): int => Guest::query()->count()))->toBe(2);
});

it('also finds duplicates by email and by ID document', function (): void {
    GuestSetup::guest(['first_name' => 'Ayesha', 'email' => 'ayesha@example.com']);
    GuestSetup::guest(['first_name' => 'John', 'id_type' => 'passport', 'id_number' => 'GB1234567']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));

    post(tenantUrl('sunrise', '/guest/guests'), guestInput(['phone' => null, 'email' => 'AYESHA@example.com', 'id_type' => null, 'id_number' => null]))
        ->assertSessionHas('duplicates', fn (array $duplicates): bool => $duplicates[0]['matched'] === ['email']);
    post(tenantUrl('sunrise', '/guest/guests'), guestInput(['phone' => null, 'email' => null, 'id_type' => 'passport', 'id_number' => 'gb 123 4567']))
        ->assertSessionHas('duplicates', fn (array $duplicates): bool => $duplicates[0]['name'] === 'John' && $duplicates[0]['matched'] === ['id']);
});

it('validates the guest form', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));

    post(tenantUrl('sunrise', '/guest/guests'), guestInput([
        'first_name' => '', 'email' => 'not-an-email', 'phone' => 'call me', 'nationality_code' => 'XX', 'id_type' => 'library_card',
        'date_of_birth' => now()->addDay()->toDateString(), 'company_id' => 999999, 'vip_level' => 'diamond',
    ]))->assertSessionHasErrors(['first_name', 'email', 'phone', 'nationality_code', 'id_type', 'date_of_birth', 'company_id', 'vip_level']);

    expect(GuestSetup::run(fn (): int => Guest::query()->count()))->toBe(0);
});

it('keeps the stored ID number when the edit form leaves it empty', function (): void {
    $guest = GuestSetup::guest(['first_name' => 'Rahim', 'id_type' => 'national_id', 'id_number' => '1990123456789']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));

    get(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/edit'))->assertOk()->assertDontSee('1990123456789')->assertSee('•••••••••6789');
    put(tenantUrl('sunrise', '/guest/guests/'.$guest->id), guestInput(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'id_number' => '']))
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/guest/guests/'.$guest->id));

    $guest = GuestSetup::fresh($guest);
    expect($guest->id_number)->toBe('1990123456789')
        ->and($guest->id_number_hash)->toBe(app(IdNumberHasher::class)->hash(IdType::NationalId, '1990123456789'))
        ->and($guest->last_name)->toBe('Uddin');
});

it('shows the full ID number and ID documents only with guest.guest.view-id', function (): void {
    $guest = GuestSetup::guest(['first_name' => 'Rahim', 'id_type' => 'passport', 'id_number' => 'AB1234567']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    post(tenantUrl('sunrise', '/core/attachments/guest/'.$guest->id), ['file' => UploadedFile::fake()->image('passport.jpg')])->assertSessionHasNoErrors();
    $media = GuestSetup::run(fn (): Media => Media::query()->sole());

    get(tenantUrl('sunrise', '/guest/guests/'.$guest->id))->assertOk()->assertSee('AB1234567')->assertSee(__('ID documents'));
    get(tenantUrl('sunrise', '/core/attachments/'.$media->id))->assertOk();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::ReservationAgent));
    get(tenantUrl('sunrise', '/guest/guests/'.$guest->id))->assertOk()->assertDontSee('AB1234567')->assertSee('•••••4567')->assertDontSee(__('ID documents'));
    get(tenantUrl('sunrise', '/core/attachments/'.$media->id))->assertForbidden();
});

it('keeps guests away from roles without guest permissions', function (): void {
    $guest = GuestSetup::guest(['first_name' => 'Rahim']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Chef));
    get(tenantUrl('sunrise', '/guest/guests'))->assertForbidden();
    get(tenantUrl('sunrise', '/guest/guests/'.$guest->id))->assertForbidden();
    post(tenantUrl('sunrise', '/guest/guests'), guestInput())->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Auditor));
    get(tenantUrl('sunrise', '/guest/guests/'.$guest->id))->assertOk();
    put(tenantUrl('sunrise', '/guest/guests/'.$guest->id), guestInput())->assertForbidden();
});

it('lists guests with search and filters, and shows the navbar quick search', function (): void {
    GuestSetup::guest(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '01711000001', 'vip_level' => 'gold']);
    GuestSetup::guest(['first_name' => 'Ayesha', 'last_name' => 'Siddique', 'phone' => '01819000002']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));

    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-quick-search');
    get(tenantUrl('sunrise', '/guest/guests?search=Rahim'))->assertOk()->assertSee('"search":{"search":"Rahim"}');

    $names = fn (string $query): array => array_map(fn (array $row): string => strip_tags($row['first_name']),
        get(tenantUrl('sunrise', '/guest/guests/data?draw=1&start=0&length=25&'.$query))->assertOk()->json('data'));

    expect($names('search[value]=rah%20udd'))->toHaveCount(1)
        ->and($names('search[value]=01819'))->toHaveCount(1)
        ->and($names('filter=vip'))->toHaveCount(1)
        ->and($names(''))->toHaveCount(2);

    expect(get(tenantUrl('sunrise', '/guest/guests/search?q=ayesha'))->assertOk()->json())->toBe([
        ['value' => GuestSetup::run(fn (): int => Guest::query()->where('first_name', 'Ayesha')->value('id')), 'text' => 'Ayesha Siddique · +8801819000002'],
    ]);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Chef));
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertDontSeeHtml('data-quick-search');
});
