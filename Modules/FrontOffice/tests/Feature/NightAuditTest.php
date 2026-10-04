<?php

/*
| Night audit (Step 2.6). "Done when": running the night audit posts one night's charges to every
| in-house folio, marks a no-show and advances the business date; running it twice for the same
| date is impossible.
|
| Rooms 401/402 (C04), 701–703 (C07), 801–803 (C08): 6,000 a night + SC 10% + VAT 15% = 7,590.00.
| The business date D is today, so after the audit (D + 1) the next audit must wait for tomorrow.
*/
use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Actions\PostRoomNights;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Core\Contracts\Settings;
use Modules\Core\Models\TaxCategory;
use Modules\FrontOffice\Actions\RunNightAudit;
use Modules\FrontOffice\Enums\NightAuditStatus;
use Modules\FrontOffice\Enums\NightAuditTrigger;
use Modules\FrontOffice\Exceptions\NightAuditNotPossible;
use Modules\FrontOffice\Jobs\RunDueNightAudits;
use Modules\FrontOffice\Models\DailyStatistic;
use Modules\FrontOffice\Models\NightAudit;
use Modules\FrontOffice\Notifications\NightAuditBlockedNotice;
use Modules\Property\Models\Property;
use Modules\Reservation\Contracts\StayOperations;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id')));
    Notification::fake();
});

function auditDay(): CarbonImmutable
{
    return CarbonImmutable::parse(booking(fn (): string => Property::query()->where('code', 'CXB')->sole()->business_date->toDateString()));
}

/**
 * A confirmed stay from D + $from for $nights nights, checked in when $checkIn.
 *
 * @param  list<string>  $units
 */
function auditStay(array $units, int $from, int $nights, bool $checkIn = true): Reservation
{
    $day = auditDay();
    $reservation = bookStay($units, $day->addDays($from)->toDateString(), $day->addDays($from + $nights)->toDateString(), ['depositPercent' => '0', 'allowDepositOverride' => true]);

    if ($checkIn) {
        booking(fn () => app(StayOperations::class)->checkIn($reservation->id));
    }

    return freshReservation($reservation->id);
}

/**
 * The stay dates of the room nights on the booking's folios.
 *
 * @return list<string>
 */
function auditPostedNights(Reservation $reservation): array
{
    return booking(fn (): array => FolioLine::query()->where('reference_type', PostRoomNights::REFERENCE)
        ->whereIn('reference_id', DB::table('reservation_item_nights')->whereIn('reservation_item_id', $reservation->items->pluck('id'))->pluck('id'))
        ->where('is_voided', false)->get()
        ->map(fn (FolioLine $line): string => (string) DB::table('reservation_item_nights')->where('id', $line->reference_id)->value('stay_date'))->sort()->values()->all());
}

/**
 * @return list<string>
 */
function auditLockDates(Reservation $reservation): array
{
    return booking(fn (): array => InventoryLock::query()->where('reservation_id', $reservation->id)->orderBy('stay_date')->get()
        ->map(fn (InventoryLock $lock): string => $lock->stay_date->toDateString())->all());
}

it('posts each in-house night up to the date, marks the no-show and moves the business date on, once', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    $day = auditDay();
    $since = auditStay(['401'], -1, 3);   // arrived yesterday: yesterday and tonight to post
    $tonight = auditStay(['402'], 0, 2);  // arrived today: tonight to post
    $missing = auditStay(['703'], 0, 2, checkIn: false);

    get(tenantUrl('sunrise', '/frontoffice/night-audit'))->assertOk()
        ->assertSeeHtml('data-audit-date="'.$day->toDateString().'"')->assertSee('3 nights to post for 2 stays in house.')
        ->assertSeeHtml('data-no-shows')->assertSee($missing->code);

    post(tenantUrl('sunrise', '/frontoffice/night-audit'), ['business_date' => $day->toDateString()])->assertSessionHas('success');

    $audit = booking(fn (): NightAudit => NightAudit::query()->sole());
    expect($audit->status)->toBe(NightAuditStatus::Completed)
        ->and([$audit->nights_posted, $audit->no_shows])->toBe([3, 1])
        ->and(auditPostedNights($since))->toBe([$day->subDay()->toDateString(), $day->toDateString()])
        ->and(auditPostedNights($tonight))->toBe([$day->toDateString()])
        ->and(freshReservation($missing->id)->status)->toBe(ReservationStatus::NoShow)
        ->and(auditDay()->toDateString())->toBe($day->addDay()->toDateString());

    // The no-show keeps its arrival night; the next night is free again.
    expect(auditLockDates($missing))->toBe([$day->toDateString()]);

    $stats = booking(fn (): DailyStatistic => DailyStatistic::query()->sole());
    expect([$stats->business_date->toDateString(), $stats->rooms_total, $stats->rooms_occupied, $stats->room_revenue, $stats->adr, $stats->no_shows])
        ->toBe([$day->toDateString(), 8, 2, '12000.00', '6000.00', 1])
        ->and($stats->occupancy_percent)->toBe('25.00');

    get(tenantUrl('sunrise', "/frontoffice/night-audit/{$audit->id}"))->assertOk()->assertSeeHtml('data-step="no_shows"')->assertSee($missing->code);

    // Twice for the same date is impossible: the date moved on, the wizard and the action refuse.
    post(tenantUrl('sunrise', '/frontoffice/night-audit'), ['business_date' => $day->toDateString()])->assertSessionHas('error');
    expect(fn () => booking(fn () => RunNightAudit::make()->handle(bookingIds()['property'])))->toThrow(NightAuditNotPossible::class)
        ->and(booking(fn (): int => NightAudit::query()->count()))->toBe(1)
        ->and(auditPostedNights($since))->toHaveCount(2)
        ->and(auditDay()->toDateString())->toBe($day->addDay()->toDateString());

    // The table refuses a second audit row of the date as well.
    expect(fn () => booking(fn () => NightAudit::factory()->create(['property_id' => bookingIds()['property'], 'business_date' => $day->toDateString()])))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('is blocked by a departure still in house, and runs once it has left', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    $day = auditDay();
    $leaving = auditStay(['401'], -2, 2); // due out today

    post(tenantUrl('sunrise', '/frontoffice/night-audit'), ['business_date' => $day->toDateString()])->assertSessionHas('error');

    $audit = booking(fn (): NightAudit => NightAudit::query()->sole());
    expect($audit->status)->toBe(NightAuditStatus::Blocked)
        ->and($audit->issues[0])->toContain($leaving->code)
        ->and(auditDay()->toDateString())->toBe($day->toDateString())
        ->and(auditPostedNights($leaving))->toBe([]);

    get(tenantUrl('sunrise', '/frontoffice/night-audit'))->assertOk()->assertSeeHtml('data-blocking')->assertSee($leaving->code);

    booking(fn () => app(StayOperations::class)->extendStay($leaving->id, $day->addDay()->toDateString()));
    post(tenantUrl('sunrise', '/frontoffice/night-audit'), ['business_date' => $day->toDateString()])->assertSessionHas('success');

    expect(booking(fn (): NightAudit => NightAudit::query()->sole())->status)->toBe(NightAuditStatus::Completed)
        ->and(auditPostedNights($leaving))->toHaveCount(3);
});

it('posts nothing when room revenue is recognised at check-out', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    booking(fn () => app(Settings::class)->set('billing.revenue_recognition', 'at_checkout'));
    $stay = auditStay(['401'], -1, 3);

    $audit = booking(fn (): NightAudit => RunNightAudit::make()->handle(bookingIds()['property']));

    expect($audit->status)->toBe(NightAuditStatus::Completed)
        ->and($audit->nights_posted)->toBe(0)
        ->and(auditPostedNights($stay))->toBe([])
        ->and(collect($audit->steps)->firstWhere('step', 'post_room_charges')['result'])->toContain('check-out');
});

it('releases the property\'s expired holds', function (): void {
    $hold = bookStay(['801'], auditDay()->addDays(5)->toDateString(), auditDay()->addDays(7)->toDateString());
    booking(fn () => Reservation::query()->whereKey($hold->id)->update(['deposit_due_at' => now()->subHour(), 'auto_cancel_unpaid' => true]));

    $audit = booking(fn (): NightAudit => RunNightAudit::make()->handle(bookingIds()['property']));

    expect($audit->holds_released)->toBe(1)
        ->and(freshReservation($hold->id)->status)->toBe(ReservationStatus::Cancelled)
        ->and(auditLockDates($hold))->toBe([]);
});

it('lets the no-show\'s payment be refunded above the no-show fee', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    $missing = bookStay(['703'], auditDay()->toDateString(), auditDay()->addDays(2)->toDateString(), ['depositPercent' => '30', 'allowDepositOverride' => true]);
    post(tenantUrl('sunrise', '/billing/payments'), ['reservation_id' => $missing->id, 'method' => 'card', 'amount' => $missing->deposit_required])->assertSessionHas('success');

    booking(fn () => RunNightAudit::make()->handle(bookingIds()['property']));
    $noShow = freshReservation($missing->id);

    // No policy charge in this setup: the whole deposit is refundable.
    expect($noShow->status)->toBe(ReservationStatus::NoShow)
        ->and($noShow->cancellation_fee)->toBe('0.00')
        ->and($noShow->logs->firstWhere('action', ReservationLogAction::NoShow)?->description)->toContain('No-show');

    post(tenantUrl('sunrise', '/billing/refunds'), ['reservation_id' => $missing->id, 'kind' => 'cancellation', 'method' => 'card', 'amount' => $missing->deposit_required, 'reason' => 'No-show, deposit returned as a goodwill gesture'])
        ->assertSessionHas('success');
});

it('runs from the scheduler at the audit time and tells the managers once when blocked', function (): void {
    $day = auditDay();
    $leaving = auditStay(['401'], -2, 2);
    $managers = [tenantUserAs(tenant('sunrise'), DefaultRole::FrontOfficeManager), tenantUserAs(tenant('sunrise'), DefaultRole::GeneralManager)];
    $desk = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent);

    // 01:00 in Dhaka the next morning: not yet (audit time 02:00).
    travelTo(CarbonImmutable::parse($day->toDateString().' 19:00', 'UTC'));
    expect(RunDueNightAudits::dispatchSync())->toBe([]);

    travelTo(CarbonImmutable::parse($day->toDateString().' 20:05', 'UTC'));
    $first = RunDueNightAudits::dispatchSync();
    RunDueNightAudits::dispatchSync();

    expect($first[0]->status)->toBe(NightAuditStatus::Blocked)->and($first[0]->trigger)->toBe(NightAuditTrigger::Scheduled);
    foreach ($managers as $manager) {
        Notification::assertSentToTimes($manager, NightAuditBlockedNotice::class, 1);
    }

    Notification::assertNotSentTo($desk, NightAuditBlockedNotice::class);

    booking(fn () => app(StayOperations::class)->checkOut($leaving->id));
    $done = RunDueNightAudits::dispatchSync();

    expect($done[0]->status)->toBe(NightAuditStatus::Completed)
        ->and(auditDay()->toDateString())->toBe($day->addDay()->toDateString());
});

it('skips properties that turned the automatic audit off, and runs from the command', function (): void {
    booking(fn () => app(Settings::class)->set('frontoffice.auto_night_audit', false, bookingIds()['property']));
    travelTo(CarbonImmutable::parse(auditDay()->toDateString().' 21:00', 'UTC'));

    expect(RunDueNightAudits::dispatchSync())->toBe([]);

    artisan('frontoffice:night-audit', ['--now' => true])->expectsOutputToContain('Night audits run: 1.')->assertSuccessful();
    expect(booking(fn (): NightAudit => NightAudit::query()->sole())->status)->toBe(NightAuditStatus::Completed);
});

it('shows the flash report: provisional for the open date, final after the audit', function (): void {
    staffUser(DefaultRole::FrontOfficeManager);
    $day = auditDay();
    auditStay(['401'], -1, 3);

    get(tenantUrl('sunrise', '/frontoffice/reports/flash?date='.$day->toDateString()))->assertOk()->assertSeeHtml('data-provisional')->assertSeeHtml('data-occupied>1<');
    get(tenantUrl('sunrise', '/frontoffice/reports/flash?date='.$day->subDays(5)->toDateString()))->assertOk()->assertSee('No report for this date');

    post(tenantUrl('sunrise', '/frontoffice/night-audit'), ['business_date' => $day->toDateString()]);

    get(tenantUrl('sunrise', '/frontoffice/reports/flash?date='.$day->toDateString()))->assertOk()
        ->assertSeeHtml('data-flash-report="'.$day->toDateString().'"')->assertDontSeeHtml('data-provisional')
        ->assertSee('15,180.00'); // two nights of room 401 posted, with taxes
    get(tenantUrl('sunrise', '/frontoffice/reports/flash?date='.$day->toDateString().'&print=1'))->assertOk()->assertSee('Flash report');
    get(tenantUrl('sunrise', '/frontoffice/reports/flash?date=yesterday'))->assertSessionHasErrors('date');
});

it('needs frontoffice.audit.view to see, frontoffice.audit.run to run, frontoffice.report.view for the report', function (): void {
    $day = auditDay()->toDateString();

    staffUser(DefaultRole::FrontDeskAgent);
    get(tenantUrl('sunrise', '/frontoffice/night-audit'))->assertForbidden();
    get(tenantUrl('sunrise', '/frontoffice/reports/flash'))->assertForbidden();

    staffUser(DefaultRole::Accountant);
    get(tenantUrl('sunrise', '/frontoffice/night-audit'))->assertOk()->assertDontSeeHtml('data-run-audit');
    get(tenantUrl('sunrise', '/frontoffice/reports/flash'))->assertOk();
    post(tenantUrl('sunrise', '/frontoffice/night-audit'), ['business_date' => $day])->assertForbidden();

    staffUser(DefaultRole::FrontOfficeManager);
    post(tenantUrl('sunrise', '/frontoffice/night-audit'), [])->assertSessionHasErrors('business_date');

    expect(auditDay()->toDateString())->toBe($day);
});

it('does not show another tenant\'s audit', function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'greenvalley'])));
    $theirs = booking(fn (): NightAudit => RunNightAudit::make()->handle(bookingIds('greenvalley')['property']), 'greenvalley');
    staffUser(DefaultRole::FrontOfficeManager);

    get(tenantUrl('sunrise', "/frontoffice/night-audit/{$theirs->id}"))->assertNotFound();
});
