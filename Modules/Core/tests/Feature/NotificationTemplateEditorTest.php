<?php

/*
| Setup → Email templates (Step 1.8): tenants rewrite the wording of registered templates.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Core\Contracts\NotificationTemplates;
use Modules\Core\Models\NotificationTemplate;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise', 'name' => 'Sunrise Resorts Ltd']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
});

/**
 * @param  array<string, string>  $data
 * @return array{0: ?string, 1: string} subject and body
 */
function renderedIn(string $slug, string $key, string $channel, array $data): array
{
    return app(TenantContext::class)->run(tenant($slug), function () use ($key, $channel, $data): array {
        $template = app(NotificationTemplates::class)->render($key, $channel, $data);

        return [$template->subject, $template->body];
    });
}

it('lists the registered templates and edits one for this tenant only', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));

    get(tenantUrl('sunrise', '/core/notification-templates'))->assertOk()
        ->assertSeeHtml('data-template="reservation.booking_created"')->assertSeeHtml('data-template="core.welcome"')->assertSee(__('Booking received'));
    get(tenantUrl('sunrise', '/core/notification-templates/mail/reservation.booking_created/edit'))->assertOk()
        ->assertSee('Your booking {code} at {property}')->assertSeeHtml('<code>{deposit_due}</code>');

    put(tenantUrl('sunrise', '/core/notification-templates/mail/reservation.booking_created'), ['locale' => 'en', 'subject' => 'Booking {code}', 'body' => "Hello {guest},\nThanks."])
        ->assertRedirect(tenantUrl('sunrise', '/core/notification-templates'))->assertSessionHas('success');

    expect(renderedIn('sunrise', 'reservation.booking_created', 'mail', ['code' => 'RSV-1', 'guest' => 'Ayesha']))->toBe(['Booking RSV-1', "Hello Ayesha,\nThanks."])
        ->and(renderedIn('greenvalley', 'reservation.booking_created', 'mail', ['code' => 'RSV-1', 'property' => 'GV'])[0])->toBe('Your booking RSV-1 at GV');

    get(tenantUrl('sunrise', '/core/notification-templates'))->assertSee(__('Your wording'));
});

it('resets a template to the default wording', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));
    app(TenantContext::class)->run(tenant('sunrise'), fn () => NotificationTemplate::factory()->create(['key' => 'core.welcome', 'channel' => 'database', 'locale' => 'en', 'subject' => 'Hi', 'body' => 'Yo']));

    delete(tenantUrl('sunrise', '/core/notification-templates/database/core.welcome'))->assertSessionHas('success');

    expect(renderedIn('sunrise', 'core.welcome', 'database', ['tenant' => 'Sunrise'])[0])->toBe('Welcome to Sunrise')
        ->and(app(TenantContext::class)->run(tenant('sunrise'), fn (): int => NotificationTemplate::query()->count()))->toBe(0);
});

it('validates the wording and 404s for unknown templates', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));

    put(tenantUrl('sunrise', '/core/notification-templates/mail/reservation.booking_created'), ['locale' => 'en', 'subject' => '', 'body' => ''])
        ->assertSessionHasErrors(['subject', 'body']);
    put(tenantUrl('sunrise', '/core/notification-templates/mail/reservation.booking_created'), ['locale' => 'xx', 'subject' => 'A', 'body' => 'B'])
        ->assertSessionHasErrors('locale');
    get(tenantUrl('sunrise', '/core/notification-templates/mail/no.such/edit'))->assertNotFound();
});

it('lets only managers change templates', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/notification-templates'))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::Auditor));
    get(tenantUrl('sunrise', '/core/notification-templates'))->assertOk();
    put(tenantUrl('sunrise', '/core/notification-templates/mail/reservation.booking_created'), ['locale' => 'en', 'subject' => 'A', 'body' => 'B'])->assertForbidden();
});
