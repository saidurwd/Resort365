<?php

/*
| The public website (Step 8.2a): a property's pages for anyone, in English and Bangla, built from the rooms, prices, offers and
| policies of the other modules; unpublished sites only as a staff preview; `/` opens the site; photos through signed links.
| Setup (booking-setup.php): Sunrise CXB, Deluxe King rooms at 6,000 a night, Family Villa cottages C07 and C08 sold whole at 15,000
| (C04 by the room only), Room Only plan sold at the front desk and online, deposit policy Standard 30%.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Modules\Core\Contracts\PublicPhotos;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\RoomType;
use Modules\Rates\Models\Promotion;
use Modules\Website\Models\WebsiteSite;
use Modules\Website\Models\WebsiteUnit;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('attachments');
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/**
 * Sunrise's site for CXB, published unless told otherwise.
 *
 * @param  array<string, mixed>  $attributes
 */
function psSite(array $attributes = []): WebsiteSite
{
    return booking(fn (): WebsiteSite => WebsiteSite::factory()->create([
        'property_id' => bookingIds()['property'], 'slug' => 'sunrise', 'name' => 'Sunrise Beach Resort',
        'tagline' => ['en' => 'Cottages by the sea', 'bn' => 'সমুদ্রের পাশে কটেজ'],
        'intro_title' => ['en' => 'Welcome to Sunrise'], 'house_rules' => ['en' => "No smoking indoors\nQuiet after 11 pm"],
        ...$attributes,
    ]));
}

function psUrl(string $path = ''): string
{
    return tenantUrl('sunrise', '/resort/sunrise'.$path);
}

/** @return array{room: int, villa: int} the Deluxe King room type and Family Villa cottage type */
function psTypes(): array
{
    return booking(fn (): array => ['room' => (int) RoomType::query()->value('id'), 'villa' => (int) CottageType::query()->value('id')]);
}

it('shows the home page and the rooms with their from prices', function (): void {
    psSite();
    $types = psTypes();

    get(psUrl())->assertOk()
        ->assertSee('Sunrise Beach Resort')->assertSee('Cottages by the sea')->assertSee('Welcome to Sunrise')
        ->assertSeeHtml('data-unit="room_type:'.$types['room'].'"')
        ->assertSeeHtml('hreflang="bn"')
        ->assertHeader('Content-Security-Policy');

    get(psUrl('/rooms'))->assertOk()
        ->assertSee('Deluxe King')->assertSee('Family Villa')
        ->assertSee('BDT 6,000')->assertSee('BDT 15,000')->assertSee('plus taxes');

    get(psUrl('/rooms/cottage/'.$types['villa']))->assertOk()->assertSee('Family Villa')->assertSee('Sleeps 11')->assertSee('the whole cottage is yours');
    get(psUrl('/rooms/room/'.$types['room']))->assertOk()->assertSee('Deluxe King')->assertSee('up to 2 adults');
    get(psUrl('/rooms/room/999999'))->assertNotFound();
});

it('uses the site texts of each room type and hides the ones marked hidden', function (): void {
    $site = psSite();
    $types = psTypes();
    booking(fn () => WebsiteUnit::factory()->create(['website_site_id' => $site->id, 'property_id' => $site->property_id, 'unit_key' => 'cottage_type:'.$types['villa'],
        'headline' => ['en' => 'Your own villa', 'bn' => 'আপনার নিজের ভিলা'], 'description' => ['en' => 'Three bedrooms and a garden.'], 'is_featured' => true]));
    $hidden = booking(fn (): WebsiteUnit => WebsiteUnit::factory()->create(['website_site_id' => $site->id, 'property_id' => $site->property_id, 'unit_key' => 'room_type:'.$types['room'], 'is_hidden' => true]));

    get(psUrl())->assertOk()->assertSee('Your own villa')->assertDontSeeHtml('data-unit="room_type:'.$types['room'].'"');
    get(psUrl('/rooms'))->assertOk()->assertDontSee('Deluxe King');
    get(psUrl('/rooms/room/'.$types['room']))->assertNotFound();
    get(psUrl('/rooms/cottage/'.$types['villa']))->assertOk()->assertSee('Three bedrooms and a garden.');

    booking(fn () => $hidden->update(['is_hidden' => false]));
    get(psUrl('/rooms'))->assertOk()->assertSee('Deluxe King');
});

it('switches to Bangla and remembers it', function (): void {
    psSite();

    get(psUrl('?lang=bn'))->assertOk()->assertSee('সমুদ্রের পাশে কটেজ')->assertSee('রুম ও কটেজ')->assertSeeHtml('<html lang="bn">');
    // A text with no Bangla falls back to English.
    get(psUrl('/policies'))->assertOk()->assertSee('নীতিমালা')->assertSee('No smoking indoors');
    get(psUrl('/rooms?lang=en'))->assertOk()->assertSeeHtml('Rooms &amp; cottages')->assertSeeHtml('<html lang="en">');
});

it('lists only the offers marked for the website', function (): void {
    psSite();
    booking(function (): void {
        Promotion::factory()->create(['property_id' => bookingIds()['property'], 'code' => null, 'name' => 'Long stay', 'discount_type' => 'percent', 'discount_value' => '10', 'min_nights' => 7, 'show_on_website' => true]);
        Promotion::factory()->create(['property_id' => bookingIds()['property'], 'code' => 'SECRET', 'name' => 'Secret deal', 'show_on_website' => false]);
        Promotion::factory()->create(['property_id' => bookingIds()['property'], 'code' => null, 'name' => 'Old offer', 'show_on_website' => true, 'stay_from' => '2020-01-01', 'stay_to' => '2020-02-01']);
    });

    get(psUrl('/offers'))->assertOk()->assertSee('Long stay')->assertSee('10% off')->assertSee('Stay at least 7 nights.')
        ->assertDontSee('Secret deal')->assertDontSee('SECRET')->assertDontSee('Old offer');
    get(psUrl())->assertOk()->assertSee('Long stay');
});

it('shows the policies, contact details and the gallery', function (): void {
    psSite(['phone' => '+880 1711 222333', 'email' => 'stay@sunrise.test', 'map_url' => 'https://maps.example/sunrise']);

    get(psUrl('/policies'))->assertOk()
        ->assertSee('Check-in from 14:00')->assertSee('A deposit of 30% of the stay secures the booking.')->assertSee('Free cancellation.')
        ->assertSee('Quiet after 11 pm');
    get(psUrl('/contact'))->assertOk()->assertSee('+880 1711 222333')->assertSeeHtml('href="tel:+8801711222333"')->assertSeeHtml('https://maps.example/sunrise');
    get(psUrl('/gallery'))->assertOk()->assertSee('Photos are coming soon.');
    // No restaurant outlets: no Dining link.
    get(psUrl())->assertOk()->assertDontSeeHtml('/resort/sunrise/dining"');
});

it('keeps an unpublished site private except as a staff preview', function (): void {
    psSite(['is_published' => false, 'published_at' => null]);

    get(psUrl())->assertNotFound();
    get(tenantUrl('sunrise', '/'))->assertRedirect(tenantUrl('sunrise', '/dashboard'));

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(psUrl())->assertNotFound();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));
    get(psUrl())->assertOk()->assertSeeHtml('data-preview')->assertSeeHtml('name="robots" content="noindex, nofollow"');
});

it('opens the website at / and lists several resorts', function (): void {
    psSite();
    get(tenantUrl('sunrise', '/'))->assertRedirect(psUrl());

    booking(fn () => WebsiteSite::factory()->create(['slug' => 'hills', 'name' => 'Hill Retreat']));
    get(tenantUrl('sunrise', '/'))->assertOk()->assertSee('Our resorts')->assertSee('Sunrise Beach Resort')->assertSee('Hill Retreat');

    DB::table('tenant_modules')->insert(['tenant_id' => tenant('sunrise')->id, 'module' => 'website', 'enabled' => false, 'created_at' => now(), 'updated_at' => now()]);
    app(ModuleAccess::class)->flush();
    get(tenantUrl('sunrise', '/'))->assertRedirect(tenantUrl('sunrise', '/dashboard'));
    get(psUrl())->assertForbidden();
});

it('serves gallery photos through signed links only', function (): void {
    $site = psSite();
    [$photo, $document] = booking(fn (): array => [
        $site->addMedia(UploadedFile::fake()->image('beach.jpg', 1200, 800))->toMediaCollection('photos'),
        $site->addMedia(UploadedFile::fake()->image('id-scan.jpg', 600, 400))->toMediaCollection('attachments'),
    ]);

    $links = booking(fn (): array => app(PublicPhotos::class)->many([$photo->id, $document->id]));
    expect($links)->toHaveCount(1)
        ->and(booking(fn (): ?string => app(PublicPhotos::class)->url($document->id)))->toBeNull();

    get(psUrl())->assertOk()->assertSeeHtml($links[0]['web']);
    get($links[0]['thumb'])->assertOk()->assertHeader('Cache-Control');
    get($links[0]['web'].'x')->assertForbidden();
    get(tenantUrl('sunrise', '/photos/'.$photo->id.'/web'))->assertForbidden();
    // A signed link to anything but a gallery photo still serves nothing.
    get(app(TenantContext::class)->run(tenant('sunrise'), fn (): string => URL::signedRoute('core.photos.show', ['media' => $document->id, 'size' => 'web'])))->assertNotFound();
});

it('never shows another company\'s site', function (): void {
    psSite();
    $moonlight = withDefaultRoles(Tenant::factory()->create(['slug' => 'moonlight']));
    app(TenantContext::class)->run($moonlight, fn () => WebsiteSite::factory()->create(['slug' => 'moon']));

    get(tenantUrl('sunrise', '/resort/moon'))->assertNotFound();
    get(tenantUrl('moonlight', '/resort/sunrise'))->assertNotFound();
    get(tenantUrl('moonlight', '/resort/moon'))->assertOk();
});
