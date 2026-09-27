<?php

use App\Models\DatabaseNotification;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Models\NotificationTemplate;
use Modules\Core\Notifications\WelcomeNotification;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd']));
});

it('stores in-app notifications with the tenant and shows them on the bell', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent, ['name' => 'Nusrat Jahan']);
    app(TenantContext::class)->run(tenant('sunrise'), fn () => $user->notify(new WelcomeNotification('Nusrat Jahan', 'Sunrise Resorts Ltd')));

    $stored = app(TenantContext::class)->run(tenant('sunrise'), fn (): ?DatabaseNotification => DatabaseNotification::query()->first());
    expect($stored?->tenant_id)->toBe(tenant('sunrise')->id)
        ->and($stored?->data['title'] ?? null)->toBe('Welcome to Sunrise Resorts Ltd');

    actingAs($user);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-notification-bell')->assertSeeHtml('data-unread-count')
        ->assertSee('Welcome to Sunrise Resorts Ltd');
});

it('marks notifications as read', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent);
    app(TenantContext::class)->run(tenant('sunrise'), function () use ($user): void {
        $user->notify(new WelcomeNotification('A', 'Sunrise'));
        $user->notify(new WelcomeNotification('B', 'Sunrise'));
    });
    actingAs($user);

    $first = app(TenantContext::class)->run(tenant('sunrise'), fn (): string => (string) $user->notifications()->value('id'));
    post(tenantUrl('sunrise', '/core/notifications/'.$first.'/read'))->assertRedirect();
    expect(app(TenantContext::class)->run(tenant('sunrise'), fn (): int => $user->unreadNotifications()->count()))->toBe(1);

    post(tenantUrl('sunrise', '/core/notifications/read-all'));
    expect(app(TenantContext::class)->run(tenant('sunrise'), fn (): int => $user->unreadNotifications()->count()))->toBe(0);

    get(tenantUrl('sunrise', '/core/notifications'))->assertOk()->assertSee('Welcome to Sunrise');
});

it('does not open another user\'s notification', function (): void {
    $owner = tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner);
    $other = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent);
    app(TenantContext::class)->run(tenant('sunrise'), fn () => $other->notify(new WelcomeNotification('Other', 'Sunrise')));
    $id = app(TenantContext::class)->run(tenant('sunrise'), fn (): string => (string) $other->notifications()->value('id'));

    actingAs($owner);
    post(tenantUrl('sunrise', '/core/notifications/'.$id.'/read'))->assertNotFound();
});

it('renders templates: tenant override, English fallback, then the default', function (): void {
    $templates = app(NotificationTemplates::class);

    app(TenantContext::class)->run(tenant('sunrise'), function () use ($templates): void {
        expect($templates->render('core.welcome', 'database', ['name' => 'Rahim', 'tenant' => 'Sunrise'])->body)
            ->toStartWith('Hi Rahim, your account is ready.');

        NotificationTemplate::query()->create(['key' => 'core.welcome', 'channel' => 'database', 'locale' => 'en', 'subject' => 'Hello {name}', 'body' => 'Welcome aboard, {name}!']);
        expect($templates->render('core.welcome', 'database', ['name' => 'Rahim'], 'bn')->subject)->toBe('Hello Rahim');

        NotificationTemplate::query()->create(['key' => 'core.welcome', 'channel' => 'database', 'locale' => 'bn', 'subject' => 'স্বাগতম {name}', 'body' => '…']);
        expect($templates->render('core.welcome', 'database', ['name' => 'রহিম'], 'bn')->subject)->toBe('স্বাগতম রহিম');
    });

    expect(fn () => app(TenantContext::class)->run(tenant('sunrise'), fn () => $templates->render('nope', 'mail')))->toThrow(InvalidArgumentException::class);
});
