<?php

/*
| The setup checklist of a new company (Step 7.4): the steps the modules register (resort, taxes, cottages and rooms, prices, team),
| each done once through its own screen, the dashboard reminder and putting it away, plan limits, and who may do it.
*/
use App\Models\Tenant;
use App\Models\TenantModule;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\Onboarding;
use Modules\Core\Database\Seeders\ReferenceDataSeeder;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;
use Modules\Platform\Actions\ChangeTenantPlan;
use Modules\Platform\Actions\SeedDefaultPlans;
use Modules\Platform\Models\Plan;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\Rate;
use Modules\Rates\Models\RatePlan;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    seed(ReferenceDataSeeder::class);
    withDefaultRoles(Tenant::factory()->create(['slug' => 'fresh']));
});

/**
 * Runs as the tenant, outside any request's property context.
 *
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function obRun(Closure $callback): mixed
{
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant('fresh'), $callback);
}

function obOwner(): void
{
    actingAs(tenantUserAs(tenant('fresh'), DefaultRole::TenantOwner));
}

/** @return array<string, bool> step key => done */
function obSteps(): array
{
    return obRun(fn (): array => collect(app(Onboarding::class)->progress()->steps)->mapWithKeys(fn (array $step): array => [$step['key'] => $step['done']])->all());
}

function obProperty(): void
{
    post(tenantUrl('fresh', '/property/onboarding/property'), [
        'name' => 'Fresh Resort', 'code' => 'frs', 'country_code' => 'BD', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT', 'check_in_time' => '14:00', 'check_out_time' => '11:00',
    ])->assertSessionHasNoErrors();
}

function obRooms(int $cottages = 2, int $rooms = 2): void
{
    post(tenantUrl('fresh', '/property/onboarding/rooms'), ['cottage_count' => $cottages, 'rooms_per_cottage' => $rooms, 'room_type_name' => 'Deluxe', 'max_occupancy' => 2]);
}

it('lists the steps in order with what is done, and reminds the owner on the dashboard', function (): void {
    obOwner();

    expect(obSteps())->toBe(['property' => false, 'taxes' => false, 'rooms' => false, 'prices' => false, 'team' => false]);
    get(tenantUrl('fresh', '/core/onboarding'))->assertOk()->assertSeeInOrder(['Your resort', 'Taxes', 'Cottages and rooms', 'Prices', 'Your team']);
    get(tenantUrl('fresh', '/dashboard'))->assertOk()->assertSeeHtml('data-onboarding-banner')->assertSee('0 of 5 steps done');
});

it('creates the resort once, with the current property set to it', function (): void {
    obOwner();
    get(tenantUrl('fresh', '/property/onboarding/property'))->assertOk()->assertSee('Create the resort');

    post(tenantUrl('fresh', '/property/onboarding/property'), ['name' => 'Fresh', 'code' => '', 'country_code' => 'XX', 'timezone' => 'Asia/Dhaka', 'currency_code' => 'BDT', 'check_in_time' => '14:00', 'check_out_time' => '11:00'])
        ->assertSessionHasErrors(['code', 'country_code']);
    obProperty();

    $property = obRun(fn (): Property => Property::query()->sole());
    expect($property->code)->toBe('FRS')->and($property->currency_code)->toBe('BDT')->and(obSteps()['property'])->toBeTrue();
    get(tenantUrl('fresh', '/property/onboarding/property'))->assertRedirect(tenantUrl('fresh', '/core/onboarding'));
});

it('sets up the taxes once: service charge, VAT on top of it, and the Room, F&B and Exempt categories', function (): void {
    obOwner();

    post(tenantUrl('fresh', '/core/onboarding/taxes'), ['service_charge_rate' => '120', 'vat_rate' => '15'])->assertSessionHasErrors('service_charge_rate');
    post(tenantUrl('fresh', '/core/onboarding/taxes'), ['service_charge_rate' => '10', 'vat_rate' => '15', 'vat_on_service_charge' => '1'])->assertSessionHasNoErrors();

    [$taxes, $categories] = obRun(fn (): array => [
        Tax::query()->orderBy('sort_order')->get()->map(fn (Tax $tax): string => $tax->code.' '.$tax->rate.($tax->is_compound ? ' compound' : ''))->all(),
        TaxCategory::query()->withCount('taxes')->orderBy('code')->get()->map(fn (TaxCategory $category): string => $category->code.':'.$category->taxes_count)->all(),
    ]);
    expect($taxes)->toBe(['SC 10.0000', 'VAT 15.0000 compound'])->and($categories)->toBe(['EXEMPT:0', 'FNB:2', 'ROOM:2'])->and(obSteps()['taxes'])->toBeTrue();

    get(tenantUrl('fresh', '/core/onboarding/taxes'))->assertRedirect(tenantUrl('fresh', '/core/onboarding'));
    post(tenantUrl('fresh', '/core/onboarding/taxes'), ['service_charge_rate' => '10', 'vat_rate' => '15'])->assertSessionHasErrors('service_charge_rate');
});

it('leaves out a tax at a rate of zero', function (): void {
    obOwner();
    post(tenantUrl('fresh', '/core/onboarding/taxes'), ['service_charge_rate' => '0', 'vat_rate' => '7.5', 'vat_on_service_charge' => '1'])->assertSessionHasNoErrors();

    expect(obRun(fn (): array => Tax::query()->get()->map(fn (Tax $tax): string => $tax->code.($tax->is_compound ? ' compound' : ''))->all()))->toBe(['VAT']);
});

it('makes the cottages and their rooms once, numbered from 101, within the plan', function (): void {
    obOwner();
    get(tenantUrl('fresh', '/property/onboarding/rooms'))->assertRedirect(tenantUrl('fresh', '/property/onboarding/property'));
    obProperty();

    app(SeedDefaultPlans::class)->handle();
    app(ChangeTenantPlan::class)->handle(tenant('fresh'), Plan::query()->where('code', 'starter')->firstOrFail());
    obRooms(11, 2);
    expect(session('error'))->toContain('Room limit reached: the Starter plan includes 20 rooms.');

    obRooms(3, 2);
    $rooms = obRun(fn (): array => Room::query()->orderBy('number')->pluck('number')->all());
    expect($rooms)->toBe(['101', '102', '103', '104', '105', '106'])->and(obSteps()['rooms'])->toBeTrue();

    get(tenantUrl('fresh', '/property/onboarding/rooms'))->assertRedirect(tenantUrl('fresh', '/core/onboarding'));
});

it('prices each kind of room with the default deposit and cancellation policies', function (): void {
    obOwner();
    obProperty();
    get(tenantUrl('fresh', '/rates/onboarding/prices'))->assertRedirect(tenantUrl('fresh', '/property/onboarding/rooms'));
    post(tenantUrl('fresh', '/core/onboarding/taxes'), ['service_charge_rate' => '10', 'vat_rate' => '15']);
    obRooms();

    $key = obRun(fn (): string => 'room_type:'.Room::query()->value('room_type_id'));
    post(tenantUrl('fresh', '/rates/onboarding/prices'), ['prices' => [$key => '']])->assertSessionHasErrors('prices');
    post(tenantUrl('fresh', '/rates/onboarding/prices'), ['prices' => [$key => '-5']])->assertSessionHasErrors('prices.'.$key);
    post(tenantUrl('fresh', '/rates/onboarding/prices'), ['prices' => [$key => '4500']])->assertSessionHasNoErrors();

    $setup = obRun(fn (): array => [
        RatePlan::query()->sole()->only(['code', 'tax_category_id']), Rate::query()->get(['rateable_type', 'amount'])->map(fn (Rate $rate): string => $rate->rateable_type->value.' '.$rate->amount)->all(),
        DepositPolicy::query()->sole()->is_default, CancellationPolicy::query()->sole()->name, TaxCategory::query()->where('code', 'ROOM')->value('id'),
    ]);
    expect($setup[0]['code'])->toBe('RO')->and($setup[0]['tax_category_id'])->toBe($setup[4])->and($setup[1])->toBe(['room_type 4500.00'])
        ->and($setup[2])->toBeTrue()->and($setup[3])->toBe('Flexible')->and(obSteps()['prices'])->toBeTrue();

    post(tenantUrl('fresh', '/rates/onboarding/prices'), ['prices' => [$key => '4500']])->assertSessionHasErrors('prices');
});

it('puts the reminder away, keeps the checklist under Setup, and hides the steps of modules the plan leaves out', function (): void {
    obOwner();

    post(tenantUrl('fresh', '/core/onboarding/dismiss'))->assertRedirect(tenantUrl('fresh', '/dashboard'));
    get(tenantUrl('fresh', '/dashboard'))->assertDontSeeHtml('data-onboarding-banner');
    get(tenantUrl('fresh', '/core/onboarding'))->assertOk()->assertDontSee('Put the checklist away');

    obRun(fn () => TenantModule::query()->create(['module' => 'rates', 'enabled' => false]));
    app(ModuleAccess::class)->flush();
    expect(array_keys(obSteps()))->toBe(['property', 'taxes', 'rooms', 'team']);
});

it('is for the people who set the company up only', function (): void {
    actingAs(tenantUserAs(tenant('fresh'), DefaultRole::FrontDeskAgent));

    get(tenantUrl('fresh', '/core/onboarding'))->assertForbidden();
    post(tenantUrl('fresh', '/core/onboarding/taxes'), ['service_charge_rate' => '10', 'vat_rate' => '15'])->assertForbidden();
    get(tenantUrl('fresh', '/property/onboarding/property'))->assertForbidden();
    get(tenantUrl('fresh', '/rates/onboarding/prices'))->assertForbidden();
    get(tenantUrl('fresh', '/dashboard'))->assertDontSeeHtml('data-onboarding-banner');
});
