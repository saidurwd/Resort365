<?php

/*
| Plan limits (Step 7.3): one more property, room, user or file than the plan includes is refused, with a message that points to an
| upgrade. "Done when": moving a tenant to Starter blocks adding a room over the limit.
*/
use App\Http\Middleware\SetCurrentProperty;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\PlanLimitReached;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Actions\AddAttachment;
use Modules\Core\Contracts\PlanLimits;
use Modules\Core\Contracts\UsageMeters;
use Modules\IAM\Actions\ActivateUser;
use Modules\IAM\Actions\DeactivateUser;
use Modules\IAM\Actions\InviteUser;
use Modules\IAM\Models\User;
use Modules\Platform\Actions\ChangeTenantPlan;
use Modules\Platform\Actions\SavePlan;
use Modules\Property\Actions\CreateCottageWithRooms;
use Modules\Property\Actions\SaveProperty;
use Modules\Property\Actions\SaveRoom;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withSession;

require_once __DIR__.'/../Support/platform-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pfSetup();
    Notification::fake();
});

/** A cottage with $rooms new rooms at CXB, through the quick form's action. */
function enCottage(int $rooms, string $code = 'X01'): void
{
    $ids = bookingIds();
    booking(fn () => app(CreateCottageWithRooms::class)->handle([
        'property_id' => $ids['property'], 'cottage_type_id' => CottageType::query()->value('id'), 'code' => $code, 'name' => $code, 'zone' => 'Garden', 'booking_mode' => 'both', 'status' => 'active',
        'room_count' => $rooms, 'room_type_id' => RoomType::query()->value('id'), 'first_room_number' => '9'.str_pad($code === 'X01' ? '01' : '51', 2, '0').'0', 'floor' => '0',
    ]));
}

it('reports the plan\'s limits and no limits for a tenant without a plan', function (): void {
    $limits = app(PlanLimits::class);

    expect(booking(fn (): ?int => $limits->limit('max_rooms')))->toBeNull()->and(booking(fn (): ?string => $limits->planName()))->toBeNull();

    pfMove('starter');

    expect(booking(fn () => $limits->limit('max_rooms')))->toBe(20)->and(booking(fn () => $limits->limit('storage_gb')))->toBe(2)->and(booking(fn () => $limits->limit('max_users')))->toBe(5)->and(booking(fn (): ?string => $limits->planName()))->toBe('Starter')
        ->and(booking(fn () => $limits->limit('nonsense')))->toBeNull();
});

it('counts what the modules say a tenant uses', function (): void {
    $meters = app(UsageMeters::class);

    expect(array_keys($meters->all()))->toContain('properties', 'rooms', 'users', 'storage')
        ->and(booking(fn () => [$meters->measure('properties'), $meters->measure('rooms'), $meters->measure('storage'), $meters->measure('nothing')]))->toBe([1, 8, 0.0, 0]);
});

it('blocks rooms over the plan: a cottage that would pass 20, then a single room (done when)', function (): void {
    pfMove('starter');

    expect(fn () => enCottage(13))->toThrow(PlanLimitReached::class, 'Room limit reached: the Starter plan includes 20 rooms. Upgrade your plan to add more.');
    enCottage(12);

    expect(booking(fn (): int => Room::query()->count()))->toBe(20);
    $single = fn () => booking(fn () => app(SaveRoom::class)->handle(null, ['property_id' => bookingIds()['property'], 'cottage_id' => bookingIds()['cottages']['C04'], 'room_type_id' => RoomType::query()->value('id'), 'number' => '999', 'is_active' => true]));

    expect($single)->toThrow(PlanLimitReached::class);

    $existing = booking(fn (): Room => Room::query()->first());
    expect(booking(fn () => app(SaveRoom::class)->handle($existing, ['name' => 'Renamed']))->name)->toBe('Renamed');
});

it('blocks a second property on a plan with one', function (): void {
    pfMove('starter');

    expect(fn () => booking(fn () => app(SaveProperty::class)->handle(null, Property::factory()->raw(['code' => 'NEW']))))->toThrow(PlanLimitReached::class, 'Property limit reached: the Starter plan includes 1 property.');

    pfMove('professional');
    $second = booking(fn () => app(SaveProperty::class)->handle(null, Property::factory()->raw(['code' => 'NEW'])));

    expect($second->code)->toBe('NEW');
});

it('blocks users over the plan, counting those invited and active, and a deactivated user coming back', function (): void {
    pfMove('starter');
    $owner = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    foreach (range(1, 3) as $n) {
        tenantUser(tenant('sunrise'), ['email' => "staff{$n}@sunrise.test"]);
    }
    booking(fn () => app(InviteUser::class)->handle('Fifth', 'fifth@sunrise.test', $owner));

    expect(booking(fn () => app(UsageMeters::class)->measure('users')))->toBe(5)
        ->and(fn () => booking(fn () => app(InviteUser::class)->handle('Sixth', 'sixth@sunrise.test', $owner)))->toThrow(PlanLimitReached::class, 'User limit reached: the Starter plan includes 5 users. Deactivate someone or upgrade your plan.');

    $staff = booking(fn () => User::query()->where('email', 'staff1@sunrise.test')->firstOrFail());
    booking(fn () => app(DeactivateUser::class)->handle($staff, $owner));
    booking(fn () => app(InviteUser::class)->handle('Sixth', 'sixth@sunrise.test', $owner));

    expect(fn () => booking(fn () => app(ActivateUser::class)->handle($staff->fresh())))->toThrow(PlanLimitReached::class);
});

it('blocks a file that would pass the storage the plan includes', function (): void {
    pfMove('starter');
    $property = booking(fn (): Property => Property::query()->firstOrFail());
    $tenantId = tenant('sunrise')->id;

    // 2.5 GB of files are kept already; the plan includes 2.
    DB::table('media')->insert([
        'tenant_id' => $tenantId, 'model_type' => 'property', 'model_id' => $property->id, 'collection_name' => 'attachments', 'name' => 'big', 'file_name' => 'big.pdf', 'mime_type' => 'application/pdf', 'disk' => 'attachments',
        'size' => 2_684_354_560, 'manipulations' => '[]', 'custom_properties' => '[]', 'generated_conversions' => '[]', 'responsive_images' => '[]', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(booking(fn () => app(UsageMeters::class)->measure('storage')))->toBe(2.5)
        ->and(fn () => booking(fn () => app(AddAttachment::class)->handle($property, UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'), null)))->toThrow(PlanLimitReached::class, 'Storage limit reached: the Starter plan includes 2 GB of files.');
});

it('tells the person on the page they were on, and a program in JSON', function (): void {
    pfMove('starter');
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    $propertyId = bookingIds()['property'];
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => $propertyId]);
    actingAs($user);
    withSession([SetCurrentProperty::SESSION_KEY => $propertyId]);
    [$cottageTypeId, $roomTypeId] = booking(fn (): array => [CottageType::query()->value('id'), RoomType::query()->value('id')]);
    $cottage = fn (int $rooms): array => ['code' => 'q01', 'name' => 'Quick', 'cottage_type_id' => $cottageTypeId, 'zone' => 'Garden', 'booking_mode' => 'both', 'status' => 'active', 'room_count' => $rooms, 'room_type_id' => $roomTypeId, 'first_room_number' => '901', 'floor' => '0'];

    post(tenantUrl('sunrise', '/property/cottages'), $cottage(13))->assertRedirect()->assertSessionHas('error', fn (string $message): bool => str_contains($message, 'Room limit reached'));
    postJson(tenantUrl('sunrise', '/property/cottages'), $cottage(13))->assertStatus(422)->assertJson(['limit' => 'max_rooms'])->assertJsonPath('message', fn (string $message): bool => str_contains($message, 'Upgrade your plan'));
    expect(booking(fn (): int => Room::query()->count()))->toBe(8);
});

it('hides the modules of a smaller plan from the sidebar and blocks their pages', function (): void {
    pfMove('starter');
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    actingAs($user);
    $html = (string) get(tenantUrl('sunrise', '/dashboard'))->assertOk()->getContent();
    preg_match('/<aside class="app-sidebar.*?<\/aside>/s', $html, $sidebar);

    expect($sidebar[0])->toContain('Reservations', 'Front Office');
    expect($sidebar[0])->not->toContain('Restaurant', 'Accounting', 'Inventory', 'Payroll');
    get(tenantUrl('sunrise', '/restaurant/outlets'))->assertForbidden();
    get(tenantUrl('sunrise', '/accounting/accounts'))->assertForbidden();

    pfMove('professional');
    get(tenantUrl('sunrise', '/restaurant/outlets'))->assertOk();
});

it('has no limits for a tenant without a plan, or on Enterprise', function (): void {
    enCottage(30);
    pfMove('enterprise');
    enCottage(30, 'X02');

    expect(booking(fn (): int => Room::query()->count()))->toBe(68)->and(booking(fn () => app(PlanLimits::class)->limit('max_rooms')))->toBeNull();
});

it('limits a custom plan the same way', function (): void {
    $plan = app(SavePlan::class)->handle(null, ['code' => 'tiny', 'name' => 'Tiny', 'monthly_price' => 1, 'yearly_price' => 10, 'currency_code' => 'USD', 'max_rooms' => 9, 'modules' => ['property']]);
    app(ChangeTenantPlan::class)->handle(tenant('sunrise'), $plan);

    expect(fn () => enCottage(2))->toThrow(PlanLimitReached::class, 'includes 9 rooms');
    enCottage(1);

    expect(booking(fn (): int => Room::query()->count()))->toBe(9);
});
