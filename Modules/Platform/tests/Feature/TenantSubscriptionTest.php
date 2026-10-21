<?php

/*
| The company's own side of its subscription (Step 7.4): Setup → Subscription with the plan, usage and invoices, the invoice PDF (its
| own only), cancelling and taking it back, the reminders, and the export of all its data from there and from the console.
*/
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Enums\TenantExportStatus;
use Modules\Core\Models\TenantExport;
use Modules\Core\Notifications\DataExportNotice;
use Modules\Guest\Models\Guest;
use Modules\Platform\Actions\StartSubscription;
use Modules\Platform\Actions\StartTrial;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Models\PlatformInvoice;
use Modules\Platform\Models\Subscription;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelBack;
use function Pest\Laravel\travelTo;

require_once __DIR__.'/../Support/platform-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    travelTo(CarbonImmutable::parse('2026-10-01 10:00'));
    Storage::fake('local');
    Storage::fake('attachments');
    $tenant = pfSetup();
    $tenant->forceFill(['status' => TenantStatus::Trial, 'trial_ends_at' => now()->addDays(14)])->save();
    app(StartTrial::class)->handle($tenant->fresh(), pfPlan('professional'), BillingPeriod::Monthly);
});

afterEach(fn () => travelBack());

function tsOwner(): void
{
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner, ['name' => 'Owner One']));
}

it('shows the plan, the usage and the invoices to the owner, and only its own invoice PDFs', function (): void {
    app(StartSubscription::class)->handle(tenant('sunrise'));
    $own = PlatformInvoice::query()->sole();
    $other = PlatformInvoice::factory()->create(['tenant_id' => Tenant::factory()->create(['slug' => 'other'])->id]);
    tsOwner();

    get(tenantUrl('sunrise', '/core/subscription'))->assertOk()->assertSee('Professional')->assertSee('Paid until')->assertSee('Rooms')->assertSee('150')->assertSee($own->number);
    get(tenantUrl('sunrise', '/core/subscription/invoices/'.$own->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    get(tenantUrl('sunrise', '/core/subscription/invoices/'.$other->id))->assertNotFound();
});

it('lets the General Manager see the subscription but not cancel it or export the data; others see nothing', function (): void {
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager));
    get(tenantUrl('sunrise', '/core/subscription'))->assertOk()->assertDontSee('Cancel the subscription')->assertDontSee('Export all data');
    post(tenantUrl('sunrise', '/core/subscription/cancel'), ['reason' => 'x', 'confirm' => '1'])->assertForbidden();
    post(tenantUrl('sunrise', '/core/data-exports'))->assertForbidden();

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/subscription'))->assertForbidden();
});

it('lets the owner cancel at the end of the trial and take it back', function (): void {
    tsOwner();

    post(tenantUrl('sunrise', '/core/subscription/cancel'), ['reason' => 'Not for us'])->assertSessionHasErrors('confirm');
    post(tenantUrl('sunrise', '/core/subscription/cancel'), ['reason' => 'Not for us', 'confirm' => '1'])->assertSessionHas('success');

    expect(Subscription::query()->sole()->cancel_at_period_end)->toBeTrue();
    get(tenantUrl('sunrise', '/dashboard'))->assertSee('the system closes on 15 Oct 2026');

    post(tenantUrl('sunrise', '/core/subscription/resume'))->assertSessionHas('success');
    expect(Subscription::query()->sole()->cancel_at_period_end)->toBeFalse();
    post(tenantUrl('sunrise', '/core/subscription/resume'))->assertSessionHasErrors('reason');
});

it('reminds the owner in the last days of the trial and of an overdue invoice', function (): void {
    tsOwner();
    get(tenantUrl('sunrise', '/dashboard'))->assertDontSeeHtml('data-subscription-banner');

    travelTo(CarbonImmutable::parse('2026-10-13 09:00'));
    get(tenantUrl('sunrise', '/dashboard'))->assertSeeHtml('data-subscription-banner="trial"')->assertSee('Your free trial ends on 14 Oct 2026');

    app(StartSubscription::class)->handle(tenant('sunrise'));
    travelTo(CarbonImmutable::parse('2026-10-21 09:00'));
    get(tenantUrl('sunrise', '/dashboard'))->assertSeeHtml('data-subscription-banner="overdue"')->assertSeeHtml('USD 149.00 is overdue');
});

it('exports all of the company\'s data for the owner, without secrets or other companies\' rows', function (): void {
    $other = withDefaultRoles(Tenant::factory()->create(['slug' => 'other']));
    app(TenantContext::class)->run($other, fn () => Guest::factory()->create(['first_name' => 'Outsider']));
    tsOwner();

    post(tenantUrl('sunrise', '/core/data-exports'))->assertRedirect()->assertSessionHas('success');

    $export = app(TenantContext::class)->run(tenant('sunrise'), fn (): TenantExport => TenantExport::query()->sole());
    expect($export->status)->toBe(TenantExportStatus::Ready)->and($export->tables_count)->toBeGreaterThan(20)->and($export->expires_at?->toDateString())->toBe('2026-10-08');

    $zip = new ZipArchive;
    $file = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents((string) $file, Storage::disk('local')->get('tenants/'.tenant('sunrise')->id.'/'.$export->path));
    $zip->open((string) $file);
    $guests = (string) $zip->getFromName('data/guests.csv');
    $users = (string) $zip->getFromName('data/users.csv');
    $zip->close();
    unlink((string) $file);

    $header = (string) strtok($users, "\n");
    expect($guests)->toContain('Rahim')->and(str_contains($guests, 'Outsider'))->toBeFalse()
        ->and($header)->toContain('email')->and(preg_match('/password|remember_token|two_factor_secret/', $header))->toBe(0);

    get(tenantUrl('sunrise', '/core/subscription'))->assertSee('Download');
    get(tenantUrl('sunrise', '/core/data-exports/'.$export->id.'/download'))->assertOk()->assertDownload($export->fileName());

    $owner = auth()->user();
    expect($owner?->notifications()->where('type', DataExportNotice::class)->count())->toBe(1);

    travelTo(CarbonImmutable::parse('2026-10-09 04:00'));
    artisan('core:purge-data-exports')->assertSuccessful();
    expect(app(TenantContext::class)->run(tenant('sunrise'), fn () => TenantExport::query()->sole()->status))->toBe(TenantExportStatus::Expired);
    get(tenantUrl('sunrise', '/core/data-exports/'.$export->id.'/download'))->assertNotFound();
});

it('lets a platform admin export and download the data of a cancelled company', function (): void {
    pfAdmin();
    $tenant = tenant('sunrise');
    $tenant->forceFill(['status' => TenantStatus::Cancelled])->save();

    post(centralUrl('/platform/tenants/'.$tenant->id.'/exports'))->assertSessionHas('success');
    $export = app(TenantContext::class)->run($tenant, fn (): TenantExport => TenantExport::query()->sole());

    get(centralUrl('/platform/tenants/'.$tenant->id))->assertOk()->assertSee('Download');
    get(centralUrl('/platform/tenants/'.$tenant->id.'/exports/'.$export->id))->assertOk()->assertDownload('sunrise-'.$export->fileName());
    expect(pfAudit('tenant.export'))->toBe(['tenant.export_requested', 'tenant.export_downloaded']);
});
