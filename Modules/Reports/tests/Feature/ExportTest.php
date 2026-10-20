<?php

/*
| Report files (Step 7.1): the Excel and PDF files, made in the background, kept for a week, announced by a notification, and
| downloaded only by the person who asked. "Done when": exports arrive as notifications.
*/

use App\Models\DatabaseNotification;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Tenancy\TenantStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Reports\DTOs\ReportTable;
use Modules\Reports\Enums\ExportFormat;
use Modules\Reports\Enums\ExportStatus;
use Modules\Reports\Jobs\GenerateReportExport;
use Modules\Reports\Models\ReportExport;
use Modules\Reports\Services\ReportExporter;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

require_once __DIR__.'/../Support/reports-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

function exAsk(int $userId, ExportFormat $format = ExportFormat::Xlsx, string $key = 'sources'): ReportExport
{
    return booking(fn (): ReportExport => ReportExport::factory()->create([
        'user_id' => $userId, 'report_key' => $key, 'title' => 'Booking sources', 'format' => $format, 'filters' => ['propertyIds' => [bookingIds()['property']], 'from' => '2026-03-01', 'to' => '2026-03-31', 'by' => 'arrival', 'limit' => 10, 'currency' => 'BDT'],
    ]));
}

/** A tenant URL, built as the job builds it. */
function exRoute(string $name, mixed ...$parameters): string
{
    return booking(fn (): string => route($name, $parameters));
}

function exRun(ReportExport $export): ReportExport
{
    booking(fn () => app()->call([new GenerateReportExport($export->id), 'handle']));

    return booking(fn (): ReportExport => ReportExport::query()->findOrFail($export->id));
}

/**
 * Notifications of a user, newest first, as [title, url].
 *
 * @return list<array{0: string, 1: string}>
 */
function exNotices(int $userId): array
{
    return booking(fn (): array => DatabaseNotification::query()->where('notifiable_id', $userId)->latest('id')->get()->map(fn ($n): array => [$n->data['title'], $n->data['url']])->all());
}

it('makes the sheet of a report: the title, the figures, the columns, the rows and the totals, with numbers as numbers', function (): void {
    $table = new ReportTable('Sources', ['Source', 'Bookings', 'Revenue'], [['Online', '3', '40,000.00']], ['Total', '3', '40,000.00'], [['label' => 'Revenue', 'value' => '40,000.00']], 'A note.');
    $sheet = app(ReportExporter::class)->sheet($table, '2026-03-01 to 2026-03-31');

    expect($sheet[0])->toBe(['Sources'])->and($sheet[1])->toBe(['2026-03-01 to 2026-03-31'])->and($sheet[3])->toBe(['Revenue', 40000.0])->and($sheet[5])->toBe(['Source', 'Bookings', 'Revenue'])
        ->and($sheet[6])->toBe(['Online', 3.0, 40000.0])->and($sheet[7])->toBe(['Total', 3.0, 40000.0])->and(end($sheet))->toBe(['A note.']);
});

it('makes real Excel and PDF files', function (): void {
    $table = new ReportTable('Sources', ['Source', 'Bookings'], [['Online', '3']], ['Total', '3']);
    $exporter = app(ReportExporter::class);

    expect(str_starts_with($exporter->make($table, ExportFormat::Xlsx, 'March'), 'PK'))->toBeTrue()->and(str_starts_with($exporter->make($table, ExportFormat::Pdf, 'March'), '%PDF'))->toBeTrue();
});

it('makes the file in the background, stores it for a week, and tells the person who asked', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    rpBook(['source' => 'online']);
    $export = exRun(exAsk($user->id));

    expect($export->status)->toBe(ExportStatus::Ready)->and($export->rows_count)->toBe(1)->and($export->path)->toStartWith('reports/')->and($export->expires_at->isAfter(now()->addDays(6)))->toBeTrue()
        ->and(booking(fn (): bool => app(TenantStorage::class)->exists((string) $export->path)))->toBeTrue()
        ->and(str_starts_with((string) booking(fn (): ?string => app(TenantStorage::class)->get((string) $export->path)), 'PK'))->toBeTrue()
        ->and(exNotices($user->id))->toBe([['Your report is ready', exRoute('reports.exports.download', $export->id)]]);
});

it('makes a PDF the same way', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    $export = exRun(exAsk($user->id, ExportFormat::Pdf));

    expect($export->status)->toBe(ExportStatus::Ready)->and(str_starts_with((string) booking(fn (): ?string => app(TenantStorage::class)->get((string) $export->path)), '%PDF'))->toBeTrue();
});

it('records a failure and says so, instead of leaving the file forever being made', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    $export = exRun(exAsk($user->id, key: 'no-such-report'));

    expect($export->status)->toBe(ExportStatus::Failed)->and($export->error)->toContain('Unknown report')->and($export->path)->toBeNull()->and(exNotices($user->id)[0][0])->toBe('Your report could not be made')
        ->and(exNotices($user->id)[0][1])->toBe(exRoute('reports.exports.index'));
});

it('makes a file once, however often the job runs', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    $export = exRun(exAsk($user->id));
    exRun($export);

    expect(exNotices($user->id))->toHaveCount(1);
});

it('lets the person who asked download the file, and nobody else', function (): void {
    $owner = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    $export = exRun(exAsk($owner->id));

    staffUser(DefaultRole::GeneralManager);
    get(tenantUrl('sunrise', "/reports/exports/{$export->id}/download"))->assertForbidden();

    actingAs($owner);
    $response = get(tenantUrl('sunrise', "/reports/exports/{$export->id}/download"));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml')->and(str_starts_with($response->streamedContent(), 'PK'))->toBeTrue();
    get(tenantUrl('sunrise', '/reports/exports'))->assertOk()->assertSeeHtml('data-export="'.$export->id.'"')->assertSeeHtml('data-download');
});

it('does not give a file that has expired or that is not made yet', function (): void {
    $owner = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    $export = exRun(exAsk($owner->id));
    $waiting = exAsk($owner->id);
    actingAs($owner);

    get(tenantUrl('sunrise', "/reports/exports/{$waiting->id}/download"))->assertNotFound();
    booking(fn () => ReportExport::query()->whereKey($export->id)->update(['expires_at' => now()->subMinute()]));
    get(tenantUrl('sunrise', "/reports/exports/{$export->id}/download"))->assertNotFound();
});

it('deletes the files that have expired and keeps the others', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager);
    $old = exRun(exAsk($user->id));
    $fresh = exRun(exAsk($user->id));
    booking(fn () => ReportExport::query()->whereKey($old->id)->update(['expires_at' => now()->subDay()]));

    artisan('reports:purge-exports')->expectsOutputToContain('1 file deleted')->assertSuccessful();

    $storage = fn (string $path): bool => booking(fn (): bool => app(TenantStorage::class)->exists($path));
    expect($storage((string) $old->path))->toBeFalse()->and($storage((string) $fresh->path))->toBeTrue()->and(booking(fn () => ReportExport::query()->find($old->id)->path))->toBeNull()->and(booking(fn () => ReportExport::query()->find($fresh->id)->path))->not->toBeNull();
});
