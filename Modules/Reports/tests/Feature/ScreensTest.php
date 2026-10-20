<?php

/*
| The dashboard, the reports hub and the report pages on screen (Step 7.1), and who may see what.
*/
use App\Models\DatabaseNotification;
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Modules\Reports\Enums\ExportFormat;
use Modules\Reports\Enums\ExportStatus;
use Modules\Reports\Jobs\GenerateReportExport;
use Modules\Reports\Models\ReportExport;
use Modules\Reports\Services\ReportFilters;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

require_once __DIR__.'/../Support/reports-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

/** The property's business date. */
function scToday(): string
{
    return booking(fn (): string => app(ReportFilters::class)->scope('')['today']);
}

it('shows the management figures of the latest audited day, the month and the year to the people who may see them', function (): void {
    $yesterday = date('Y-m-d', strtotime(scToday().' -1 day'));
    rpStat($yesterday, ['rooms_available' => 8, 'rooms_occupied' => 6, 'room_revenue' => '39000.00', 'fnb_sales' => '8000.00']);
    staffUser(DefaultRole::GeneralManager);

    $page = get(tenantUrl('sunrise', '/dashboard'));

    $page->assertOk()->assertSeeHtml('data-kpis')->assertSeeHtml('data-chart="trend"')->assertSeeHtml('data-chart="months"')->assertSeeHtml('data-period="year"');
    $page->assertSee('75.0%')->assertSee('6,500.00')->assertSee('4,875.00')->assertSee('39,000.00')->assertSee('8,000.00');
});

it('shows a new tenant with no property yet the welcome page, not an error', function (): void {
    $bare = withDefaultRoles(Tenant::factory()->create(['slug' => 'bare']));
    actingAs(tenantUserAs($bare, DefaultRole::TenantOwner));

    get(tenantUrl('bare', '/dashboard'))->assertOk()->assertSee('Welcome to')->assertDontSeeHtml('data-kpis');
    get(tenantUrl('bare', '/reports/forecast'))->assertForbidden();
});

it('says so when no night audit has run yet', function (): void {
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-stats-date')->assertSee('No night audit has run yet');
});

it('shows each person only the parts their role may see', function (): void {
    rpStat(date('Y-m-d', strtotime(scToday().' -1 day')), ['fnb_sales' => '8000.00']);

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSee('Welcome to')->assertDontSeeHtml('data-kpis');

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-desk')->assertSeeHtml('data-kpi="arrivals"')->assertDontSeeHtml('data-kpis');

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-kpis')->assertSeeHtml('data-cash')->assertSeeHtml('data-cash-total');

    staffUser(DefaultRole::FnbManager);
    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-kpis')->assertSeeHtml('F&amp;B sales');
});

it('offers all properties of the same currency, and adds them up', function (): void {
    $other = rpProperty('GVR');
    $yesterday = date('Y-m-d', strtotime(scToday().' -1 day'));
    rpStat($yesterday, ['rooms_available' => 8, 'rooms_occupied' => 4, 'room_revenue' => '20000.00']);
    rpStat($yesterday, ['rooms_available' => 12, 'rooms_occupied' => 8, 'room_revenue' => '60000.00'], $other);
    staffUser(DefaultRole::TenantOwner);

    get(tenantUrl('sunrise', '/dashboard'))->assertOk()->assertSeeHtml('data-dashboard-scope')->assertSee('50.0%')->assertSee('20,000.00');
    // 12 of 20 room nights sold; 80,000 over 12 sold rooms = 6,666.67; over 20 available = 4,000.
    get(tenantUrl('sunrise', '/dashboard?property=all'))->assertOk()->assertSee('60.0%')->assertSee('6,666.67')->assertSee('4,000.00')->assertSee('80,000.00')->assertSee('All properties');
});

it('lists the reports a person may open and links the other modules\' reports', function (): void {
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/reports'))->assertOk()->assertSeeHtml('data-report="forecast"')->assertSeeHtml('data-report="top-companies"')->assertSeeHtml('data-report-links')->assertSee('Booking sources')->assertSee('Financial reports')->assertSeeHtml('data-my-files');

    staffUser(DefaultRole::FnbManager);
    get(tenantUrl('sunrise', '/reports'))->assertOk()->assertDontSeeHtml('data-report="forecast"')->assertSee('Sales')->assertDontSeeHtml('data-my-files');

    staffUser(DefaultRole::Waiter);
    get(tenantUrl('sunrise', '/reports'))->assertForbidden();
});

it('shows a report with its filters, and works with the dates asked for', function (): void {
    rpBook(['source' => 'online', 'created_at' => '2026-03-01 10:00:00'], rooms: 2);
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/reports/sources?from=2026-03-01&to=2026-03-31&by=arrival'))->assertOk()->assertSeeHtml('data-report-filter')->assertSee('Booking sources 2026-03-01 to 2026-03-31')->assertSee('Online')->assertSee('10,000.00')->assertSeeHtml('data-export-xlsx');
    get(tenantUrl('sunrise', '/reports/forecast?horizon=60'))->assertOk()->assertSee('Occupancy forecast')->assertSee('Rooms to sell');
    get(tenantUrl('sunrise', '/reports/top-guests?limit=5'))->assertOk()->assertSee('Top guests');
    get(tenantUrl('sunrise', '/reports/sources?from=2026-03-31&to=2026-03-01'))->assertSessionHasErrors('to');
    get(tenantUrl('sunrise', '/reports/sources?by=weekly'))->assertSessionHasErrors('by');
    get(tenantUrl('sunrise', '/reports/no-such-report'))->assertNotFound();
});

it('downloads a report as CSV at once', function (): void {
    rpBook(['source' => 'online']);
    staffUser(DefaultRole::GeneralManager);

    $csv = get(tenantUrl('sunrise', '/reports/sources?from=2026-03-01&to=2026-03-31&by=arrival&export=csv'));

    $csv->assertOk();
    expect($csv->streamedContent())->toContain('Source,Bookings')->toContain('Online,1,0,2,"10,000.00"');
});

it('queues an Excel or PDF file and says it will be announced', function (): void {
    Queue::fake();
    staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/reports/sources?from=2026-03-01&to=2026-03-31&export=xlsx'))->assertRedirect()->assertSessionHas('success');
    get(tenantUrl('sunrise', '/reports/forecast?horizon=30&export=pdf'))->assertRedirect()->assertSessionHas('success');

    Queue::assertPushed(GenerateReportExport::class, 2);
    $files = booking(fn () => ReportExport::query()->orderBy('id')->get());
    expect($files)->toHaveCount(2)->and($files[0]->format)->toBe(ExportFormat::Xlsx)->and($files[0]->status)->toBe(ExportStatus::Queued)->and($files[0]->report_key)->toBe('sources')->and($files[0]->filters['from'])->toBe('2026-03-01')
        ->and($files[0]->filters['propertyIds'])->toBe([bookingIds()['property']])->and($files[1]->format)->toBe(ExportFormat::Pdf);
});

it('makes the file and sends the notification when the queue runs', function (): void {
    $user = staffUser(DefaultRole::GeneralManager);

    get(tenantUrl('sunrise', '/reports/sources?from=2026-03-01&to=2026-03-31&export=xlsx'))->assertRedirect();

    $file = booking(fn () => ReportExport::query()->firstOrFail());
    expect($file->status)->toBe(ExportStatus::Ready)->and(exNoticeCount($user->id))->toBe(1);
});

it('keeps the reports from those who may not see them, and the files from those who may not ask', function (): void {
    staffUser(DefaultRole::FnbManager);
    get(tenantUrl('sunrise', '/reports/forecast'))->assertForbidden();
    get(tenantUrl('sunrise', '/reports/top-guests'))->assertForbidden();
    get(tenantUrl('sunrise', '/reports/exports'))->assertForbidden();

    staffUser(DefaultRole::Auditor);
    get(tenantUrl('sunrise', '/reports/forecast'))->assertOk();
    get(tenantUrl('sunrise', '/reports/forecast?export=xlsx'))->assertForbidden();
});

function exNoticeCount(int $userId): int
{
    return booking(fn (): int => DatabaseNotification::query()->where('notifiable_id', $userId)->count());
}
