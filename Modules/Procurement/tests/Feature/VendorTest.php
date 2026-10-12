<?php

/*
| Vendors (Step 5.5): profile, contacts, price list and the price a vendor offers on a day.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Procurement\Actions\SaveVendor;
use Modules\Procurement\Actions\SaveVendorContact;
use Modules\Procurement\Actions\SaveVendorPrice;
use Modules\Procurement\Exceptions\ProcurementRuleViolated;
use Modules\Procurement\Models\Vendor;
use Modules\Procurement\Models\VendorContact;
use Modules\Procurement\Models\VendorItem;
use Modules\Procurement\Services\VendorPrices;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/procurement-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    pcPrepare();
});

it('numbers vendors, keeps codes unique and checks the rating', function (): void {
    $first = pcVendor('Fresh Farm');
    $second = pcVendor('Bay Fisheries', 'sales@bay.example');

    expect($first->code)->toBe('VND-0001')->and($second->code)->toBe('VND-0002')
        ->and(fn () => booking(fn () => app(SaveVendor::class)->handle(null, ['code' => 'vnd-0001', 'name' => 'Copy', 'currency' => 'BDT'])))->toThrow(ProcurementRuleViolated::class, 'already has the code')
        ->and(fn () => booking(fn () => app(SaveVendor::class)->handle($first, ['name' => 'Fresh Farm', 'currency' => 'BDT', 'rating' => 6])))->toThrow(ProcurementRuleViolated::class, 'between 1 and 5');

    $changed = booking(fn (): Vendor => app(SaveVendor::class)->handle($first, ['name' => 'Fresh Farm Ltd', 'currency' => 'bdt', 'payment_terms_days' => 21, 'rating' => 4, 'status' => 'inactive', 'bank_name' => 'City Bank']));
    expect($changed->code)->toBe('VND-0001')->and($changed->name)->toBe('Fresh Farm Ltd')->and($changed->currency)->toBe('BDT')->and($changed->rating)->toBe(4)->and($changed->status->value)->toBe('inactive');
});

it('keeps one primary contact', function (): void {
    $vendor = pcVendor();
    booking(fn () => app(SaveVendorContact::class)->handle($vendor, null, ['name' => 'Anwar', 'email' => 'anwar@x.example', 'is_primary' => true]));
    booking(fn () => app(SaveVendorContact::class)->handle($vendor, null, ['name' => 'Mizan', 'is_primary' => true]));

    expect(booking(fn (): array => VendorContact::query()->orderBy('id')->pluck('is_primary', 'name')->all()))->toBe(['Anwar' => false, 'Mizan' => true]);
});

it('validates price list entries', function (): void {
    $vendor = pcVendor();
    $rice = pcItem('Rice');
    $save = fn (array $data) => booking(fn () => app(SaveVendorPrice::class)->handle($vendor, null, ['item_id' => $rice->id, ...$data]));

    expect(fn () => $save(['price' => '0']))->toThrow(ProcurementRuleViolated::class, 'more than zero')
        ->and(fn () => $save(['price' => '10', 'valid_from' => '2026-05-02', 'valid_to' => '2026-05-01']))->toThrow(ProcurementRuleViolated::class, 'cannot end before')
        ->and(fn () => booking(fn () => app(SaveVendorPrice::class)->handle($vendor, null, ['item_id' => 999999, 'price' => '10'])))->toThrow(ProcurementRuleViolated::class, 'Unknown item');
});

it('offers the lowest valid price for the quantity', function (): void {
    $vendor = pcVendor();
    $rice = pcItem('Rice');
    pcPrice($vendor, $rice, '100');
    pcPrice($vendor, $rice, '90', ['min_quantity' => '50']);
    pcPrice($vendor, $rice, '70', ['valid_from' => '2026-07-01', 'valid_to' => '2026-07-31']);
    $price = fn (string $day, string $quantity) => booking(fn (): ?string => app(VendorPrices::class)->price($vendor->id, $rice->id, CarbonImmutable::parse($day), $quantity));

    expect($price('2026-06-15', '10'))->toBe('100.0000')->and($price('2026-06-15', '50'))->toBe('90.0000')->and($price('2026-07-15', '10'))->toBe('70.0000')->and($price('2026-08-01', '10'))->toBe('100.0000')
        ->and(booking(fn (): ?string => app(VendorPrices::class)->price($vendor->id, 999999, CarbonImmutable::parse('2026-06-15'))))->toBeNull();
});

it('manages vendors through the screens', function (): void {
    staffUser(DefaultRole::ProcurementOfficer);
    $rice = pcItem('Rice');

    get(tenantUrl('sunrise', '/procurement/vendors'))->assertOk()->assertSeeHtml('data-new-vendor');
    get(tenantUrl('sunrise', '/procurement/vendors/new'))->assertOk()->assertSeeHtml('data-vendor-form');
    post(tenantUrl('sunrise', '/procurement/vendors'), ['name' => '', 'currency' => 'BDT', 'payment_terms_days' => 30, 'status' => 'active'])->assertSessionHasErrors('name');
    post(tenantUrl('sunrise', '/procurement/vendors'), ['name' => 'Cool Drinks', 'email' => 'not-an-email', 'currency' => 'BDT', 'payment_terms_days' => 30, 'status' => 'active'])->assertSessionHasErrors('email');
    post(tenantUrl('sunrise', '/procurement/vendors'), ['name' => 'Cool Drinks', 'email' => 'orders@cool.example', 'currency' => 'BDT', 'payment_terms_days' => 15, 'status' => 'active', 'rating' => 3])->assertSessionHasNoErrors()->assertSessionHas('success');

    $vendor = booking(fn (): Vendor => Vendor::query()->sole());
    get(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}"))->assertOk()->assertSee('Cool Drinks')->assertSeeHtml('data-contact-form')->assertSeeHtml('data-price-form');
    get(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}/edit"))->assertOk();
    put(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}"), ['name' => 'Cool Drinks Ltd', 'currency' => 'BDT', 'payment_terms_days' => 15, 'status' => 'active'])->assertSessionHas('success');

    post(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}/contacts"), ['name' => 'Rafiq', 'email' => 'rafiq@cool.example', 'is_primary' => '1'])->assertSessionHas('success');
    post(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}/prices"), ['item_id' => $rice->id, 'price' => '0'])->assertSessionHasErrors('price');
    post(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}/prices"), ['item_id' => $rice->id, 'price' => '12.5', 'min_quantity' => '10'])->assertSessionHas('success');
    get(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}"))->assertSee('Rafiq')->assertSeeHtml('data-price="'.$rice->sku.'"')->assertSee('12.5000');

    $contact = booking(fn (): VendorContact => VendorContact::query()->sole());
    $entry = booking(fn (): VendorItem => VendorItem::query()->sole());
    delete(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}/contacts/{$contact->id}"))->assertSessionHas('success');
    delete(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}/prices/{$entry->id}"))->assertSessionHas('success');
    expect(booking(fn (): int => VendorContact::query()->count() + VendorItem::query()->count()))->toBe(0);

    get(tenantUrl('sunrise', '/procurement/vendors?q=cool&status=active'))->assertOk()->assertSeeHtml('data-vendor="VND-0001"');
    get(tenantUrl('sunrise', '/procurement/vendors?status=inactive'))->assertOk()->assertDontSeeHtml('data-vendor="VND-0001"');
});

it('keeps vendors to the people who hold the permissions', function (): void {
    $vendor = pcVendor();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/procurement/vendors'))->assertForbidden();
    get(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}"))->assertForbidden();

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/procurement/vendors'))->assertOk()->assertDontSeeHtml('data-new-vendor');
    get(tenantUrl('sunrise', "/procurement/vendors/{$vendor->id}"))->assertOk()->assertDontSeeHtml('data-contact-form');
    get(tenantUrl('sunrise', '/procurement/vendors/new'))->assertForbidden();
    post(tenantUrl('sunrise', '/procurement/vendors'), ['name' => 'X', 'currency' => 'BDT', 'payment_terms_days' => 1, 'status' => 'active'])->assertForbidden();
});
