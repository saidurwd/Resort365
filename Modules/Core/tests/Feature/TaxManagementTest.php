<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
});

/**
 * Service charge 10% then VAT 15% (compound), grouped as "Room".
 *
 * @return array{Tax, Tax, TaxCategory}
 */
function roomTaxes(): array
{
    return app(TenantContext::class)->run(tenant('sunrise'), function (): array {
        $sc = Tax::factory()->create(['code' => 'SC', 'name' => 'Service charge', 'rate' => '10', 'sort_order' => 10]);
        $vat = Tax::factory()->compound()->create(['code' => 'VAT', 'name' => 'VAT', 'rate' => '15', 'sort_order' => 20]);
        $room = TaxCategory::factory()->create(['code' => 'ROOM', 'name' => 'Room']);
        $room->taxes()->attach([$sc->id => ['tenant_id' => $room->tenant_id], $vat->id => ['tenant_id' => $room->tenant_id]]);

        return [$sc, $vat, $room];
    });
}

it('creates taxes and a category, and calculates with them', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Accountant));

    post(tenantUrl('sunrise', '/core/taxes'), ['code' => 'sc', 'name' => 'Service charge', 'type' => 'percent', 'rate' => '10', 'sort_order' => 10])
        ->assertSessionHasNoErrors()->assertRedirect(tenantUrl('sunrise', '/core/taxes'));
    post(tenantUrl('sunrise', '/core/taxes'), ['code' => 'VAT', 'name' => 'VAT', 'type' => 'percent', 'rate' => '15', 'sort_order' => 20, 'is_compound' => '1'])
        ->assertSessionHasNoErrors();

    $ids = app(TenantContext::class)->run(tenant('sunrise'), fn (): array => Tax::query()->orderBy('sort_order')->pluck('id')->all());
    post(tenantUrl('sunrise', '/core/tax-categories'), ['code' => 'room', 'name' => 'Room', 'tax_ids' => $ids])->assertSessionHasNoErrors();

    $category = app(TenantContext::class)->run(tenant('sunrise'), fn (): TaxCategory => TaxCategory::query()->sole());
    $breakdown = app(TenantContext::class)->run(tenant('sunrise'), fn () => app(TaxEngine::class)->calculate('1000', $category->id));

    expect($category->code)->toBe('ROOM')
        ->and($breakdown->gross)->toBe('1265.00')
        ->and(app(TenantContext::class)->run(tenant('sunrise'), fn () => app(TaxEngine::class)->calculate('1265', $category->id, inclusive: true)->net))->toBe('1000.00')
        ->and(app(TenantContext::class)->run(tenant('sunrise'), fn () => app(TaxEngine::class)->calculate('1000', null)->gross))->toBe('1000.00');

    get(tenantUrl('sunrise', '/core/taxes?amount=1000&category='.$category->id))->assertOk()
        ->assertSeeInOrder(['Service charge', '100.00', 'VAT', '165.00', '1,265.00']);
});

it('validates taxes and categories', function (): void {
    roomTaxes();
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));

    post(tenantUrl('sunrise', '/core/taxes'), ['code' => 'VAT', 'name' => '', 'type' => 'levy', 'rate' => '-1', 'sort_order' => 1])
        ->assertSessionHasErrors(['code', 'name', 'type', 'rate']);
    post(tenantUrl('sunrise', '/core/taxes'), ['code' => 'X', 'name' => 'X', 'type' => 'percent', 'rate' => '150', 'sort_order' => 1])->assertSessionHasErrors('rate');
    post(tenantUrl('sunrise', '/core/taxes'), ['code' => 'LEVY', 'name' => 'Levy', 'type' => 'fixed', 'rate' => '150', 'sort_order' => 1])->assertSessionHasNoErrors();

    $other = withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));
    $foreignTax = app(TenantContext::class)->run($other, fn (): Tax => Tax::factory()->create());
    post(tenantUrl('sunrise', '/core/tax-categories'), ['code' => 'ROOM', 'name' => 'Room again', 'tax_ids' => [$foreignTax->id]])
        ->assertSessionHasErrors(['code', 'tax_ids.0']);
});

it('updates a category\'s taxes with an audit entry, and refuses to delete a tax in use', function (): void {
    [$sc, $vat, $room] = roomTaxes();
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Accountant));

    delete(tenantUrl('sunrise', '/core/taxes/'.$vat->id))->assertSessionHas('error');
    put(tenantUrl('sunrise', '/core/tax-categories/'.$room->id), ['code' => 'ROOM', 'name' => 'Room', 'tax_ids' => [$sc->id]])->assertSessionHasNoErrors();
    get(tenantUrl('sunrise', '/core/tax-categories/'.$room->id.'/edit'))->assertOk()->assertSee('Service charge, VAT');

    delete(tenantUrl('sunrise', '/core/taxes/'.$vat->id))->assertRedirect(tenantUrl('sunrise', '/core/taxes'));
    expect(app(TenantContext::class)->run(tenant('sunrise'), fn (): array => Tax::query()->pluck('code')->all()))->toBe(['SC']);
});

it('stops charging an inactive tax', function (): void {
    [, $vat, $room] = roomTaxes();
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));

    put(tenantUrl('sunrise', '/core/taxes/'.$vat->id), ['code' => 'VAT', 'name' => 'VAT', 'type' => 'percent', 'rate' => '15', 'sort_order' => 20, 'is_compound' => '1', 'is_active' => '0'])
        ->assertSessionHasNoErrors();

    expect(app(TenantContext::class)->run(tenant('sunrise'), fn () => app(TaxEngine::class)->calculate('1000', $room->id)->gross))->toBe('1100.00');
});

it('lets front office managers view taxes but only managers and accountants change them', function (): void {
    [$sc] = roomTaxes();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontOfficeManager));
    get(tenantUrl('sunrise', '/core/taxes'))->assertOk()->assertDontSee(__('New tax'));
    post(tenantUrl('sunrise', '/core/taxes'), [])->assertForbidden();
    delete(tenantUrl('sunrise', '/core/taxes/'.$sc->id))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/taxes'))->assertForbidden();
});
