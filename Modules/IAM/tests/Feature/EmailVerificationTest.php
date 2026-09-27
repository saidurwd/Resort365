<?php

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('verifies the email through the signed link and resends on request', function (): void {
    Notification::fake();
    $tenant = Tenant::factory()->create(['slug' => 'sunrise']);
    $user = tenantUser($tenant, [], fn ($factory) => $factory->unverified());
    actingAs($user);

    post(tenantUrl('sunrise', '/email/verification-notification'))->assertSessionHas('status', 'verification-link-sent');
    Notification::assertSentTo($user, VerifyEmail::class);

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['tenant' => 'sunrise', 'id' => $user->id, 'hash' => sha1($user->email)]);

    get($url)->assertRedirect();
    expect(app(TenantContext::class)->run($tenant, fn () => $user->fresh()?->hasVerifiedEmail()))->toBeTrue();
    get(tenantUrl('sunrise', '/dashboard'))->assertOk();
});
