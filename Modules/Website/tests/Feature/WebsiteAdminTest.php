<?php

/*
| Website back office (Step 8.2a): the current property's site settings and texts per language, how its room and cottage types
| appear, and the "Show on the website" flag of promotions.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;
use Modules\Rates\Models\Promotion;
use Modules\Website\Enums\SiteTheme;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Models\WebsiteUnit;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function waInput(array $overrides = []): array
{
    return [
        'name' => 'Sunrise Beach Resort', 'slug' => 'sunrise-cxb', 'theme' => 'ocean', 'is_published' => '1',
        'phone' => '+880 1711 000000', 'email' => 'stay@sunrise.test', 'map_url' => 'https://maps.example/sunrise',
        'tagline' => ['en' => 'Cottages by the sea', 'bn' => 'সমুদ্রের পাশে কটেজ'],
        'intro_body' => ['en' => 'Welcome.', 'bn' => ''],
        ...$overrides,
    ];
}

function waSite(): ?WebsiteSite
{
    return booking(fn (): ?WebsiteSite => WebsiteSite::query()->first());
}

it('creates and publishes the property\'s website with texts per language', function (): void {
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/website/site'))->assertOk()->assertSee(__('No website yet'))->assertSeeHtml('data-texts="bn"')->assertSeeHtml('value="cxb"');

    put(tenantUrl('sunrise', '/website/site'), waInput())->assertRedirect(tenantUrl('sunrise', '/website/site'))->assertSessionHas('success');

    $site = waSite();
    expect($site?->slug)->toBe('sunrise-cxb')
        ->and($site?->theme)->toBe(SiteTheme::Ocean)
        ->and($site?->is_published)->toBeTrue()
        ->and($site?->published_at)->not->toBeNull()
        ->and($site?->tagline)->toBe(['en' => 'Cottages by the sea', 'bn' => 'সমুদ্রের পাশে কটেজ'])
        ->and($site?->intro_body)->toBe(['en' => 'Welcome.'])
        ->and($site?->property_id)->toBe(bookingIds()['property']);

    get(tenantUrl('sunrise', '/website/site'))->assertOk()->assertSeeHtml('data-preview-link')->assertSee('সমুদ্রের পাশে কটেজ');
    get(tenantUrl('sunrise', '/resort/sunrise-cxb'))->assertOk()->assertSee('Sunrise Beach Resort');

    put(tenantUrl('sunrise', '/website/site'), waInput(['is_published' => '0']))->assertSessionHas('success');
    expect(waSite()?->is_published)->toBeFalse()->and(waSite()?->published_at)->toBeNull();
});

it('validates the web address and the links', function (): void {
    staffUser(DefaultRole::GeneralManager);
    booking(fn () => WebsiteSite::factory()->create(['slug' => 'taken', 'property_id' => Property::factory()->create(['code' => 'HIL'])->id]));

    put(tenantUrl('sunrise', '/website/site'), waInput(['slug' => 'Not OK!']))->assertSessionHasErrors('slug');
    put(tenantUrl('sunrise', '/website/site'), waInput(['slug' => 'api']))->assertSessionHasErrors('slug');
    put(tenantUrl('sunrise', '/website/site'), waInput(['slug' => 'taken']))->assertSessionHasErrors('slug');
    put(tenantUrl('sunrise', '/website/site'), waInput(['map_url' => 'javascript:alert(1)', 'theme' => 'neon', 'name' => '']))->assertSessionHasErrors(['map_url', 'theme', 'name']);
    put(tenantUrl('sunrise', '/website/site'), waInput(['tagline' => ['fr' => 'Bonjour']]))->assertSessionHasErrors('tagline');

    expect(booking(fn (): int => WebsiteSite::query()->where('property_id', bookingIds()['property'])->count()))->toBe(0);
});

it('lets only the managers see and change the website', function (): void {
    staffUser(DefaultRole::FrontDeskAgent);

    get(tenantUrl('sunrise', '/website/site'))->assertForbidden();
    put(tenantUrl('sunrise', '/website/site'), waInput())->assertForbidden();
    get(tenantUrl('sunrise', '/dashboard'))->assertDontSee(__('Site settings'));

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/website/site'))->assertOk()->assertDontSee(__('Save website'));
    put(tenantUrl('sunrise', '/website/site'), waInput())->assertForbidden();
});

it('sets how each room type and cottage type appears', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    get(tenantUrl('sunrise', '/website/units'))->assertRedirect(tenantUrl('sunrise', '/website/site'));

    put(tenantUrl('sunrise', '/website/site'), waInput());
    $site = waSite();
    $villa = 'cottage_type:'.booking(fn (): int => (int) CottageType::query()->value('id'));

    get(tenantUrl('sunrise', '/website/units'))->assertOk()->assertSeeHtml('data-unit-row="'.$villa.'"')->assertSee('Family Villa');

    put(tenantUrl('sunrise', '/website/units/'.$site?->id), ['units' => [$villa => [
        'headline' => ['en' => 'Your own villa', 'bn' => 'আপনার নিজের ভিলা'], 'description' => ['en' => '', 'bn' => ''], 'is_featured' => '1', 'is_hidden' => '0', 'sort_order' => '2',
    ]]])->assertRedirect(tenantUrl('sunrise', '/website/units'))->assertSessionHas('success');

    $unit = booking(fn (): WebsiteUnit => WebsiteUnit::query()->sole());
    expect([$unit->unit_key, $unit->headline, $unit->description, $unit->is_featured, $unit->sort_order])->toBe([$villa, ['en' => 'Your own villa', 'bn' => 'আপনার নিজের ভিলা'], null, true, 2]);

    put(tenantUrl('sunrise', '/website/units/'.$site?->id), ['units' => ['room_type:999999' => ['is_hidden' => '1']]])->assertSessionHasErrors('units');
});

it('marks a promotion for the website', function (): void {
    staffUser(DefaultRole::GeneralManager);
    $promotion = booking(fn (): Promotion => Promotion::factory()->create(['property_id' => bookingIds()['property'], 'name' => 'Long stay']));

    get(tenantUrl('sunrise', '/rates/promotions/'.$promotion->id.'/edit'))->assertOk()->assertSeeHtml('name="show_on_website"');
    put(tenantUrl('sunrise', '/rates/promotions/'.$promotion->id), [
        'name' => 'Long stay', 'discount_type' => 'percent', 'discount_value' => '10', 'min_nights' => 7, 'is_active' => '1', 'show_on_website' => '1',
    ])->assertSessionHasNoErrors();

    expect(booking(fn (): bool => Promotion::query()->findOrFail($promotion->id)->show_on_website))->toBeTrue();
});

it('creates a promotion that stays off the website by default', function (): void {
    staffUser(DefaultRole::GeneralManager);

    post(tenantUrl('sunrise', '/rates/promotions'), ['name' => 'Weekday deal', 'discount_type' => 'percent', 'discount_value' => '5', 'is_active' => '1'])->assertSessionHasNoErrors();

    expect(booking(fn (): bool => Promotion::query()->where('name', 'Weekday deal')->sole()->show_on_website))->toBeFalse();
});
