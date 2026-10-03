<?php

use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Media;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\Enums\VipLevel;
use Modules\Guest\Events\GuestsMerged;
use Modules\Guest\Models\Guest;
use Modules\Guest\Tests\Support\GuestSetup;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('attachments');
    GuestSetup::tenant();
});

it('merges a duplicate into the kept profile', function (): void {
    Event::fake([GuestsMerged::class]);
    $keep = GuestSetup::guest(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '01711000001', 'preferences' => ['Non-smoking'], 'notes' => 'Prefers sea view.']);
    $duplicate = GuestSetup::guest(['title' => 'Mr', 'first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '01711000001', 'email' => 'rahim@example.com',
        'id_type' => 'national_id', 'id_number' => '1990123456789', 'vip_level' => 'gold', 'preferences' => ['Non-smoking', 'Quiet room'], 'notes' => 'Booked by phone.']);

    $user = tenantUserAs(tenant('sunrise'), DefaultRole::FrontOfficeManager);
    actingAs($user);
    post(tenantUrl('sunrise', '/core/attachments/guest/'.$duplicate->id), ['file' => UploadedFile::fake()->image('nid.jpg')])->assertSessionHasNoErrors();

    get(tenantUrl('sunrise', '/guest/guests/'.$keep->id))->assertOk()->assertSee(__('Merge into this profile'))->assertSee(__('Same phone'));
    post(tenantUrl('sunrise', '/guest/guests/'.$keep->id.'/merge'), ['duplicate_id' => $duplicate->id])
        ->assertRedirect(tenantUrl('sunrise', '/guest/guests/'.$keep->id))->assertSessionHas('success');

    $keep = GuestSetup::fresh($keep);
    $duplicate = GuestSetup::fresh($duplicate);

    expect($keep->title)->toBe('Mr')
        ->and($keep->email)->toBe('rahim@example.com')
        ->and($keep->id_number)->toBe('1990123456789')
        ->and($keep->id_number_hash)->toBe($duplicate->id_number_hash)
        ->and($keep->vip_level)->toBe(VipLevel::Gold)
        ->and($keep->preferences)->toBe(['Non-smoking', 'Quiet room'])
        ->and($keep->notes)->toBe("Prefers sea view.\n\nBooked by phone.")
        ->and($duplicate->trashed())->toBeTrue()
        ->and($duplicate->merged_into_id)->toBe($keep->id)
        ->and(GuestSetup::run(fn (): array => Media::query()->pluck('model_id')->all()))->toBe([$keep->id])
        ->and(GuestSetup::run(fn () => app(GuestLookup::class)->find($duplicate->id)?->id))->toBe($keep->id);

    Event::assertDispatched(GuestsMerged::class, fn (GuestsMerged $event): bool => $event->keptGuestId === $keep->id && $event->mergedGuestId === $duplicate->id);

    get(tenantUrl('sunrise', '/guest/guests/'.$duplicate->id))->assertNotFound();
});

it('refuses to merge a guest into itself, and keeps merging to managers', function (): void {
    $guest = GuestSetup::guest(['first_name' => 'Rahim']);
    $other = GuestSetup::guest(['first_name' => 'Karim']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));
    post(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/merge'), ['duplicate_id' => $guest->id])->assertSessionHasErrors('duplicate_id');
    post(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/merge'), ['duplicate_id' => 999999])->assertSessionHasErrors('duplicate_id');

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/guest/guests/'.$guest->id))->assertOk()->assertDontSee(__('Merge another guest into this profile'));
    post(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/merge'), ['duplicate_id' => $other->id])->assertForbidden();

    expect(GuestSetup::run(fn (): int => Guest::query()->count()))->toBe(2);
});

it('blacklists a guest with a reason and clears it again', function (): void {
    $guest = GuestSetup::guest(['first_name' => 'Kamal', 'last_name' => 'Hossain']);
    $manager = tenantUserAs(tenant('sunrise'), DefaultRole::FrontOfficeManager);

    actingAs($manager);
    post(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/blacklist'), ['reason' => ''])->assertSessionHasErrors('reason');
    post(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/blacklist'), ['reason' => 'Left without paying.'])->assertSessionHas('warning');

    $guest = GuestSetup::fresh($guest);
    expect($guest->is_blacklisted)->toBeTrue()
        ->and($guest->blacklist_reason)->toBe('Left without paying.')
        ->and($guest->blacklisted_by)->toBe($manager->id)
        ->and(GuestSetup::run(fn (): bool => app(GuestLookup::class)->isBlacklisted($guest->id)))->toBeTrue();

    get(tenantUrl('sunrise', '/guest/guests/'.$guest->id))->assertOk()->assertSee('Left without paying.');
    get(tenantUrl('sunrise', '/guest/guests/data?draw=1&start=0&length=25&filter=blacklisted'))->assertJsonCount(1, 'data');

    delete(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/blacklist'))->assertSessionHas('success');
    expect(GuestSetup::fresh($guest)->is_blacklisted)->toBeFalse();
});

it('keeps the blacklist to managers', function (): void {
    $guest = GuestSetup::guest(['first_name' => 'Kamal']);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    post(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/blacklist'), ['reason' => 'x'])->assertForbidden();
    delete(tenantUrl('sunrise', '/guest/guests/'.$guest->id.'/blacklist'))->assertForbidden();

    expect(GuestSetup::fresh($guest)->is_blacklisted)->toBeFalse();
});
