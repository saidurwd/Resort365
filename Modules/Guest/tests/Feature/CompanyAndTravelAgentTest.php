<?php

use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Guest\Models\Company;
use Modules\Guest\Models\TravelAgent;
use Modules\Guest\Tests\Support\GuestSetup;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    GuestSetup::tenant();
});

it('manages companies with a credit limit and payment terms', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Accountant));

    get(tenantUrl('sunrise', '/guest/companies/create'))->assertOk()->assertSee('BDT');
    post(tenantUrl('sunrise', '/guest/companies'), [
        'name' => 'Meghna Group', 'tax_number' => 'BIN-000123456', 'credit_limit' => '500000', 'payment_terms_days' => 30,
        'address' => ['line1' => 'Gulshan 2', 'city' => 'Dhaka', 'country_code' => 'BD'], 'is_active' => '1',
    ])->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/guest/companies'));
    post(tenantUrl('sunrise', '/guest/companies'), ['name' => '', 'credit_limit' => '12.345', 'payment_terms_days' => 400])
        ->assertSessionHasErrors(['name', 'credit_limit', 'payment_terms_days']);

    $company = GuestSetup::run(fn (): Company => Company::query()->sole());
    expect($company->credit_limit)->toBe('500000.00')->and($company->address)->toBe(['line1' => 'Gulshan 2', 'city' => 'Dhaka', 'country_code' => 'BD']);

    get(tenantUrl('sunrise', '/guest/companies'))->assertOk()->assertSee('500,000.00');
    put(tenantUrl('sunrise', '/guest/companies/'.$company->id), ['name' => 'Meghna Group', 'credit_limit' => '0', 'payment_terms_days' => 0, 'is_active' => '0'])
        ->assertSessionHasNoErrors();
    expect(GuestSetup::run(fn (): bool => (bool) Company::query()->value('is_active')))->toBeFalse();

    delete(tenantUrl('sunrise', '/guest/companies/'.$company->id))->assertRedirect(tenantUrl('sunrise', '/guest/companies'));
    expect(GuestSetup::run(fn (): int => Company::query()->count()))->toBe(0);
});

it('manages travel agents with a commission', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontOfficeManager));

    post(tenantUrl('sunrise', '/guest/travel-agents'), ['code' => 'sbt', 'name' => 'Sundarban Tours', 'commission_percent' => '12.5', 'credit_limit' => '50000'])
        ->assertSessionHasNoErrors();
    post(tenantUrl('sunrise', '/guest/travel-agents'), ['code' => 'SBT', 'name' => 'Copy', 'commission_percent' => '120', 'credit_limit' => '-1'])
        ->assertSessionHasErrors(['code', 'commission_percent', 'credit_limit']);

    $agent = GuestSetup::run(fn (): TravelAgent => TravelAgent::query()->sole());
    expect($agent->code)->toBe('SBT')->and($agent->commission_percent)->toBe('12.50');

    get(tenantUrl('sunrise', '/guest/travel-agents'))->assertOk()->assertSee('12.5%');
    get(tenantUrl('sunrise', '/guest/travel-agents/'.$agent->id.'/edit'))->assertOk();
});

it('lets the right roles see and change companies and travel agents', function (): void {
    [$company, $agent] = GuestSetup::run(fn (): array => [Company::factory()->create(), TravelAgent::factory()->create()]);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/guest/companies'))->assertOk();
    get(tenantUrl('sunrise', '/guest/travel-agents'))->assertOk();
    post(tenantUrl('sunrise', '/guest/companies'), ['name' => 'X'])->assertForbidden();
    put(tenantUrl('sunrise', '/guest/travel-agents/'.$agent->id), [])->assertForbidden();
    delete(tenantUrl('sunrise', '/guest/companies/'.$company->id))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Chef));
    get(tenantUrl('sunrise', '/guest/companies'))->assertForbidden();
});
