<?php

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

it('creates a tenant and prints its URL', function (): void {
    artisan('tenant:create', ['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd', '--email' => 'info@sunrise.test'])
        ->expectsOutputToContain('http://sunrise.'.config('tenancy.central_domain'))
        ->assertSuccessful();

    assertDatabaseHas('tenants', ['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd', 'email' => 'info@sunrise.test', 'status' => 'active']);
});

it('creates a trial tenant with a trial end date', function (): void {
    artisan('tenant:create', ['slug' => 'trialco', 'name' => 'Trial Co', '--status' => 'trial'])->assertSuccessful();

    $tenant = Tenant::query()->where('slug', 'trialco')->sole();

    expect($tenant->status)->toBe(TenantStatus::Trial)
        ->and($tenant->trial_ends_at?->isFuture())->toBeTrue();
});

it('rejects invalid slugs', function (string $slug, string $message): void {
    Tenant::factory()->create(['slug' => 'taken']);

    artisan('tenant:create', ['slug' => $slug, 'name' => 'Some Resort'])
        ->expectsOutputToContain($message)
        ->assertFailed();

    assertDatabaseCount('tenants', 1);
})->with([
    'reserved' => ['www', 'reserved'],
    'duplicate' => ['taken', 'already been taken'],
    'bad characters' => ['bad_slug', 'format is invalid'],
    'leading hyphen' => ['-sunrise', 'format is invalid'],
    'too short' => ['ab', 'at least 3'],
]);

it('rejects an unknown status', function (): void {
    artisan('tenant:create', ['slug' => 'sunrise', 'name' => 'Sunrise', '--status' => 'paused'])->assertFailed();

    assertDatabaseCount('tenants', 0);
});

it('seeds the two demo tenants idempotently', function (): void {
    seed(DemoSeeder::class);
    seed(DemoSeeder::class);

    expect(Tenant::query()->orderBy('slug')->pluck('name', 'slug')->all())->toBe([
        'greenvalley' => 'Green Valley Resort',
        'sunrise' => 'Sunrise Resorts Ltd',
    ]);
});
