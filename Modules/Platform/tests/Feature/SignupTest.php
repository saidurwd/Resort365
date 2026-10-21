<?php

/*
| Self-service sign-up (Step 7.4): the form and its address check, a new company on a trial with its owner, the one-time welcome link,
| and the "Done when": a brand-new resort signs up, works through the setup checklist and takes its first booking with no other help.
*/
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Guest\Models\Guest;
use Modules\IAM\Models\User;
use Modules\Platform\Actions\SeedDefaultPlans;
use Modules\Platform\Enums\SubscriptionStatus;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\Subscription;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Rates\Models\RatePlan;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(SeedDefaultPlans::class)->handle();
    Notification::fake();
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function suForm(array $overrides = []): array
{
    return [
        'company' => 'Blue Lagoon Resort', 'slug' => 'bluelagoon', 'name' => 'Nusrat Jahan', 'email' => 'Owner@BlueLagoon.test', 'password' => 'S3cure-Passw0rd!',
        'password_confirmation' => 'S3cure-Passw0rd!', 'plan_id' => Plan::query()->where('code', 'professional')->value('id'), 'billing_period' => 'monthly', 'terms' => '1', ...$overrides,
    ];
}

/**
 * Signs up through the form and returns the welcome link it sends the owner to.
 *
 * @param  array<string, mixed>  $overrides
 */
function suSignUp(array $overrides = []): string
{
    $response = post(centralUrl('/signup'), suForm($overrides))->assertRedirect();

    return (string) $response->headers->get('Location');
}

it('shows the sign-up form with the active plans, and the home page links to it', function (): void {
    Plan::query()->where('code', 'enterprise')->update(['is_active' => false]);

    get(centralUrl('/signup'))->assertOk()->assertSee('Start your free trial')->assertSee('Starter')->assertSee('Professional')->assertDontSee('Enterprise');
    get(centralUrl('/'))->assertOk()->assertSeeHtml(centralUrl('/signup'));
});

it('checks whether an address is free', function (): void {
    Tenant::factory()->create(['slug' => 'taken']);

    getJson(centralUrl('/signup/check?slug=bluelagoon'))->assertOk()->assertJson(['available' => true]);
    getJson(centralUrl('/signup/check?slug=taken'))->assertJson(['available' => false, 'message' => 'That address is taken. Try another.']);
    getJson(centralUrl('/signup/check?slug=admin'))->assertJson(['available' => false, 'message' => 'That address is reserved.']);
    getJson(centralUrl('/signup/check?slug=Bad_Name'))->assertJson(['available' => false]);
});

it('creates the company on a trial of the chosen plan with its owner, and sends the owner to the new address', function (): void {
    $link = suSignUp();
    $tenant = Tenant::query()->where('slug', 'bluelagoon')->firstOrFail();

    expect($link)->toStartWith(tenantUrl('bluelagoon', '/iam/welcome/'))
        ->and($tenant->status)->toBe(TenantStatus::Trial)->and($tenant->name)->toBe('Blue Lagoon Resort')->and($tenant->email)->toBe('owner@bluelagoon.test')
        ->and($tenant->trial_ends_at?->toDateString())->toBe(now()->addDays(14)->toDateString())
        ->and($tenant->plan_id)->toBe(Plan::query()->where('code', 'professional')->value('id'));

    $subscription = Subscription::currentFor($tenant->id);
    expect($subscription?->status)->toBe(SubscriptionStatus::Trialing)->and($subscription?->trial_ends_at?->toDateString())->toBe($tenant->trial_ends_at?->toDateString());

    $owner = app(TenantContext::class)->run($tenant, fn (): User => User::query()->sole());
    expect($owner->email)->toBe('owner@bluelagoon.test')->and(app(TenantContext::class)->run($tenant, fn (): bool => $owner->hasRole(DefaultRole::TenantOwner->value)))->toBeTrue()->and($owner->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($owner, VerifyEmail::class);
});

it('signs the owner in with the welcome link once, then asks for the password', function (): void {
    $link = suSignUp();
    $owner = app(TenantContext::class)->run(tenant('bluelagoon'), fn (): User => User::query()->sole());

    get($link)->assertRedirect(tenantUrl('bluelagoon', '/core/onboarding'));
    assertAuthenticatedAs($owner);

    auth()->guard('web')->logout();
    get($link)->assertRedirect(tenantUrl('bluelagoon', '/login'));
    assertGuest();
});

it('refuses a sign-up with a taken or reserved address, a weak password, no terms, or a filled hidden field', function (array $overrides, string $field): void {
    Tenant::factory()->create(['slug' => 'taken']);
    $before = Tenant::query()->count();

    post(centralUrl('/signup'), suForm($overrides))->assertSessionHasErrors($field);

    expect(Tenant::query()->count())->toBe($before);
})->with([
    'taken address' => [['slug' => 'taken'], 'slug'],
    'reserved address' => [['slug' => 'www'], 'slug'],
    'weak password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'terms not accepted' => [['terms' => null], 'terms'],
    'bot' => [['website' => 'http://spam.test'], 'website'],
    'inactive plan' => [['plan_id' => 999999], 'plan_id'],
]);

it('lets a brand-new resort set itself up and take its first booking (Done when)', function (): void {
    seed(ReferenceDataSeeder::class);
    get(suSignUp());
    $tenant = tenant('bluelagoon');
    $owner = app(TenantContext::class)->run($tenant, fn (): User => User::query()->sole());
    $owner->forceFill(['email_verified_at' => now()])->save();
    actingAs($owner);

    get(tenantUrl('bluelagoon', '/core/onboarding'))->assertOk()->assertSee('0 of 5 steps done')->assertSee('Your resort')->assertSee('Prices');

    post(tenantUrl('bluelagoon', '/property/onboarding/property'), [
        'name' => 'Blue Lagoon', 'code' => 'blg', 'country_code' => 'BD', 'city' => "Cox's Bazar", 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT', 'check_in_time' => '14:00', 'check_out_time' => '12:00',
    ])->assertRedirect(tenantUrl('bluelagoon', '/core/onboarding'))->assertSessionHasNoErrors();
    post(tenantUrl('bluelagoon', '/core/onboarding/taxes'), ['service_charge_rate' => '10', 'vat_rate' => '15', 'vat_on_service_charge' => '1'])->assertSessionHasNoErrors();
    post(tenantUrl('bluelagoon', '/property/onboarding/rooms'), ['cottage_count' => '3', 'rooms_per_cottage' => '2', 'room_type_name' => 'Garden room', 'max_occupancy' => '3'])->assertSessionHasNoErrors();

    $unit = app(TenantContext::class)->run($tenant, fn (): string => 'room_type:'.Room::query()->value('room_type_id'));
    get(tenantUrl('bluelagoon', '/rates/onboarding/prices'))->assertOk()->assertSee('Garden room');
    post(tenantUrl('bluelagoon', '/rates/onboarding/prices'), ['prices' => [$unit => '5000']])->assertSessionHasNoErrors();

    get(tenantUrl('bluelagoon', '/core/onboarding'))->assertOk()->assertSee('4 of 5 steps done');

    $reservation = app(TenantContext::class)->run($tenant, function (): Reservation {
        app(PropertyContext::class)->clear();
        $guest = Guest::factory()->create(['first_name' => 'Karim', 'last_name' => 'Ahmed']);

        return CreateReservation::make()->handle(NewReservation::from([
            'propertyId' => Property::query()->value('id'), 'checkIn' => CarbonImmutable::today()->addDays(7), 'checkOut' => CarbonImmutable::today()->addDays(9),
            'items' => [new BookingItem(ItemType::Room, (int) Room::query()->where('number', '101')->value('id'), (int) RatePlan::query()->value('id'), 2)], 'primaryGuestId' => $guest->id,
        ]));
    });

    // Two nights at 5,000 with service charge 10% and VAT 15% on top of it: 10,000 × 1.10 × 1.15.
    expect(app(TenantContext::class)->run($tenant, fn (): array => [Room::query()->count(), (string) $reservation->grand_total]))->toBe([6, '12650.00']);
});
