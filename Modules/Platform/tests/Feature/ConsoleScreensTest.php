<?php

/*
| The platform console screens (Step 7.3): overview, tenants, plans, announcements and the audit log, behind the platform sign-in.
*/

use App\Enums\TenantStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Models\Announcement;
use Modules\Platform\Models\Plan;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

require_once __DIR__.'/../Support/platform-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    pfSetup();
});

it('sends someone who is not a platform admin to the sign-in page', function (): void {
    foreach (['/platform/tenants', '/platform/plans', '/platform/announcements', '/platform/audit'] as $path) {
        get(centralUrl($path))->assertRedirect(centralUrl('/platform/login'));
    }
});

it('shows the overview, the tenants with their plans and a tenant page with usage', function (): void {
    pfAdmin();
    pfMove('starter');

    get(centralUrl('/platform'))->assertOk()->assertSee('Platform Admin');
    get(centralUrl('/platform/tenants'))->assertOk()->assertSee('sunrise')->assertSee('Starter');
    get(centralUrl('/platform/tenants?q=zzz'))->assertOk()->assertDontSee('sunrise');
    get(centralUrl('/platform/tenants/'.tenant('sunrise')->id))->assertOk()->assertSee('Starter')->assertSee('Rooms');
});

it('changes the plan from the tenant page and warns about what is over the limits', function (): void {
    pfAdmin();
    $tiny = Plan::factory()->create(['code' => 'tiny', 'name' => 'Tiny', 'max_rooms' => 5, 'modules' => ['property']]);
    $path = '/platform/tenants/'.tenant('sunrise')->id.'/plan';

    put(centralUrl($path), ['plan_id' => $tiny->id])->assertRedirect()->assertSessionHas('warning', fn (string $message): bool => str_contains($message, 'Rooms: 8 in use'));
    expect(tenant('sunrise')->plan_id)->toBe($tiny->id);

    put(centralUrl($path), ['plan_id' => 999999])->assertSessionHasErrors('plan_id');
    put(centralUrl($path), ['plan_id' => ''])->assertRedirect();

    expect(tenant('sunrise')->plan_id)->toBeNull();
});

it('suspends and reactivates from the tenant page, asking for a reason', function (): void {
    pfAdmin();
    $base = '/platform/tenants/'.tenant('sunrise')->id;

    post(centralUrl($base.'/suspend'), ['reason' => ''])->assertSessionHasErrors('reason');
    post(centralUrl($base.'/suspend'), ['reason' => 'Unpaid invoice'])->assertRedirect();

    expect(tenant('sunrise')->status)->toBe(TenantStatus::Suspended);

    post(centralUrl($base.'/reactivate'))->assertRedirect();

    expect(tenant('sunrise')->status)->toBe(TenantStatus::Active);
});

it('makes, changes and deletes plans in the console', function (): void {
    pfAdmin();
    $data = ['code' => 'gold', 'name' => 'Gold', 'monthly_price' => '99', 'yearly_price' => '990', 'currency_code' => 'USD', 'max_rooms' => '40', 'modules' => ['restaurant'], 'is_active' => '1'];

    get(centralUrl('/platform/plans'))->assertOk()->assertSee('Starter')->assertSee('Enterprise');
    get(centralUrl('/platform/plans/create'))->assertOk();
    post(centralUrl('/platform/plans'), [...$data, 'code' => 'x y'])->assertSessionHas('error');
    post(centralUrl('/platform/plans'), $data)->assertRedirect(centralUrl('/platform/plans'));

    $gold = Plan::query()->where('code', 'gold')->firstOrFail();
    expect($gold->max_rooms)->toBe(40)->and($gold->max_users)->toBeNull();

    get(centralUrl('/platform/plans/'.$gold->id.'/edit'))->assertOk()->assertSee('Gold');
    put(centralUrl('/platform/plans/'.$gold->id), [...$data, 'name' => 'Gold plus'])->assertRedirect();
    expect($gold->fresh()->name)->toBe('Gold plus');

    pfMove('starter');
    delete(centralUrl('/platform/plans/'.pfPlan('starter')->id))->assertRedirect()->assertSessionHas('error');
    delete(centralUrl('/platform/plans/'.$gold->id))->assertRedirect();

    expect(Plan::query()->where('code', 'gold')->exists())->toBeFalse();
});

it('makes, changes and deletes announcements in the console', function (): void {
    pfAdmin();
    $data = ['title' => 'Maintenance tonight', 'body' => 'Short downtime at 02:00.', 'level' => 'warning', 'audience' => 'all'];

    get(centralUrl('/platform/announcements'))->assertOk();
    get(centralUrl('/platform/announcements/create'))->assertOk();
    post(centralUrl('/platform/announcements'), [...$data, 'level' => 'loud'])->assertSessionHasErrors('level');
    post(centralUrl('/platform/announcements'), $data)->assertRedirect();

    $announcement = Announcement::query()->firstOrFail();
    get(centralUrl('/platform/announcements'))->assertSee('Maintenance tonight');
    put(centralUrl('/platform/announcements/'.$announcement->id), [...$data, 'title' => 'Maintenance moved'])->assertRedirect();
    expect($announcement->fresh()->title)->toBe('Maintenance moved');

    delete(centralUrl('/platform/announcements/'.$announcement->id))->assertRedirect();

    expect(Announcement::query()->count())->toBe(0);
});

it('lists the audit log and filters it', function (): void {
    pfAdmin();
    pfMove('starter');

    get(centralUrl('/platform/audit'))->assertOk()->assertSee('tenant.plan_changed');
    get(centralUrl('/platform/audit?action=plan.'))->assertOk()->assertDontSee('tenant.plan_changed');
});
