<?php

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Models\Activity;
use Modules\Core\Models\Media;
use Tests\Fixtures\Tenancy\IsolationProbe;
use Tests\Fixtures\Tenancy\IsolationProbePolicy;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('attachments');
    Relation::morphMap(['isolation-probe' => IsolationProbe::class]);
    Gate::policy(IsolationProbe::class, IsolationProbePolicy::class);

    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
    withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley']));
});

/**
 * An upload with real PDF bytes (the media library checks the content, not just the extension).
 */
function pdf(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF\n");
}

function probeIn(string $slug): IsolationProbe
{
    return app(TenantContext::class)->run(tenant($slug), fn (): IsolationProbe => IsolationProbe::factory()->create());
}

function mediaOf(string $slug, IsolationProbe $probe): ?Media
{
    return app(TenantContext::class)->run(tenant($slug), fn (): ?Media => Media::query()->where('model_id', $probe->id)->first());
}

it('uploads a file to a record under the tenant\'s folder and audits it', function (): void {
    $probe = probeIn('sunrise');
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner, ['name' => 'Rahim Uddin']));

    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => pdf('passport.pdf')])
        ->assertSessionHasNoErrors()->assertSessionHas('success');

    $media = mediaOf('sunrise', $probe);
    expect($media?->file_name)->toBe('passport.pdf')
        ->and($media?->getCustomProperty('uploaded_by_name'))->toBe('Rahim Uddin');
    Storage::disk('attachments')->assertExists('tenants/'.tenant('sunrise')->id.'/media/'.$media?->id.'/passport.pdf');

    expect(app(TenantContext::class)->run(tenant('sunrise'), fn (): ?string => Activity::query()->latest('id')->value('description')))
        ->toBe('Attachment "passport.pdf" added');
});

it('downloads a file only through the authorized route', function (): void {
    $probe = probeIn('sunrise');
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => pdf('invoice.pdf')]);
    $media = mediaOf('sunrise', $probe);

    get(tenantUrl('sunrise', '/core/attachments/'.$media?->id))->assertOk();

    post(tenantUrl('sunrise', '/logout'));
    get(tenantUrl('sunrise', '/core/attachments/'.$media?->id))->assertRedirect(tenantUrl('sunrise', '/login'));
});

it('follows the record\'s policy for uploading and deleting', function (): void {
    $probe = probeIn('sunrise');
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => pdf('a.pdf')]);
    $media = mediaOf('sunrise', $probe);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent));
    get(tenantUrl('sunrise', '/core/attachments/'.$media?->id))->assertOk();
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => pdf('b.pdf')])->assertForbidden();
    delete(tenantUrl('sunrise', '/core/attachments/'.$media?->id))->assertForbidden();

    actingAs(userIn('sunrise', tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager)->email));
    delete(tenantUrl('sunrise', '/core/attachments/'.$media?->id))->assertSessionHas('success');
    expect(mediaOf('sunrise', $probe))->toBeNull();
});

it('hides another tenant\'s files and records', function (): void {
    $theirs = probeIn('greenvalley');
    actingAs(tenantUserAs(tenant('greenvalley'), DefaultRole::TenantOwner));
    post(tenantUrl('greenvalley', '/core/attachments/isolation-probe/'.$theirs->id), ['file' => pdf('secret.pdf')]);
    $media = mediaOf('greenvalley', $theirs);

    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    get(tenantUrl('sunrise', '/core/attachments/'.$media?->id))->assertNotFound();
    delete(tenantUrl('sunrise', '/core/attachments/'.$media?->id))->assertNotFound();
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$theirs->id), ['file' => pdf('x.pdf')])->assertNotFound();
});

it('rejects unknown types, disallowed files and oversized files', function (): void {
    $probe = probeIn('sunrise');
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));

    post(tenantUrl('sunrise', '/core/attachments/nothing/'.$probe->id), ['file' => pdf('a.pdf')])->assertNotFound();
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => UploadedFile::fake()->createWithContent('disguised.pdf', "MZ\x90\x00\x03 this is a program")])
        ->assertSessionHasErrors(['file' => __('This file type is not allowed.')]);
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => UploadedFile::fake()->create('huge.pdf', 20_000, 'application/pdf')])->assertSessionHasErrors('file');

    expect(mediaOf('sunrise', $probe))->toBeNull();
});

it('renders the attachments component for a record', function (): void {
    $probe = probeIn('sunrise');
    actingAs(tenantUserAs(tenant('sunrise'), DefaultRole::TenantOwner));
    post(tenantUrl('sunrise', '/core/attachments/isolation-probe/'.$probe->id), ['file' => pdf('contract.pdf')]);

    app(TenantContext::class)->run(tenant('sunrise'), function () use ($probe): void {
        blade('<x-attachments :subject="$probe" />', ['probe' => $probe->fresh()])
            ->assertSee('contract.pdf')
            ->assertSee('/core/attachments/isolation-probe/'.$probe->id, false)
            ->assertSee('data-attachment', false);
    });
});
