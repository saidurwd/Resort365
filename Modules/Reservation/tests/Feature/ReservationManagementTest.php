<?php

/*
| Reservation management (Step 1.7): the list, the reservation page, guests, the deposit and
| cancellation (ARCHITECTURE §6.5, §6.7).
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Guest\Actions\MergeGuests;
use Modules\Guest\Models\Guest;
use Modules\Rates\Actions\DeleteRatePlan;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Exceptions\RatePlanInUse;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\CancellationPolicyRule;
use Modules\Rates\Models\RatePlan;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Actions\ChangeDeposit;
use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Enums\ReservationStatus;
use Modules\Reservation\Events\ReservationCancelled;
use Modules\Reservation\Events\ReservationConfirmed;
use Modules\Reservation\Exceptions\DepositBelowMinimum;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\ReservationGuest;

use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\post;
use function Pest\Laravel\put;
use function Pest\Laravel\travelTo;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
});

describe('list', function (): void {
    it('lists the property\'s reservations with filters and search by number or guest', function (): void {
        staffUser();
        $first = bookStay(['401'], '2026-11-10', '2026-11-13');
        $second = bookStay(['402'], '2026-12-01', '2026-12-03');
        booking(fn () => CancelReservation::make()->handle($second, 'Duplicate booking'));

        get(tenantUrl('sunrise', '/reservation/bookings'))->assertOk()->assertSeeHtml('id="reservations-table"')->assertSeeHtml('data-reservation-filters');

        $rows = fn (string $query): array => getJson(tenantUrl('sunrise', '/reservation/bookings/data'.$query))->assertOk()->json('data');

        expect(array_map(fn (string $name): bool => str_contains($name, 'Rahim Uddin'), array_column($rows('?draw=1&start=0&length=25'), 'guest')))->toBe([true, true])
            ->and($rows('?draw=1&start=0&length=25&status=cancelled'))->toHaveCount(1)
            ->and($rows('?draw=1&start=0&length=25&from=2026-11-01&to=2026-11-30')[0]['code'] ?? '')->toContain($first->code)
            ->and($rows('?draw=1&start=0&length=25&search[value]='.$first->code))->toHaveCount(1)
            ->and($rows('?draw=1&start=0&length=25&search[value]=rahim'))->toHaveCount(2)
            ->and($rows('?draw=1&start=0&length=25&search[value]=nobody'))->toHaveCount(0);
    });

    it('rejects bad filters and staff without reservation.booking.view', function (): void {
        staffUser();
        get(tenantUrl('sunrise', '/reservation/bookings?status=lost'))->assertSessionHasErrors('status');

        staffUser(DefaultRole::HousekeepingSupervisor);
        get(tenantUrl('sunrise', '/reservation/bookings'))->assertForbidden();
        getJson(tenantUrl('sunrise', '/reservation/bookings/data'))->assertForbidden();
    });
});

describe('reservation page', function (): void {
    it('shows the summary, rooms, guests, payments and history tabs', function (): void {
        staffUser();
        $reservation = bookStay(['401', 'C07']);

        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertOk()
            ->assertSeeHtml('data-tab-hash="summary"')->assertSeeHtml('data-tab-hash="rooms"')->assertSeeHtml('data-tab-hash="guests"')
            ->assertSeeHtml('data-tab-hash="payments"')->assertSeeHtml('data-tab-hash="history"')
            ->assertSee('Lagoon Villa (whole cottage)')->assertSee('Rahim Uddin')
            ->assertSeeHtml('data-action="modify"')->assertSeeHtml('data-action="cancel"')
            ->assertSee(__('Created'));
    });

    it('hides the payments tab and the actions from staff who may only look', function (): void {
        staffUser(DefaultRole::Auditor);
        $reservation = bookStay(['401']);

        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertOk()
            ->assertSeeHtml('data-tab-hash="payments"') // auditors view every *.view permission
            ->assertDontSeeHtml('data-action="modify"')->assertDontSeeHtml('data-action="cancel"')->assertDontSeeHtml('data-take-payment');
    });

    it('hides the payments tab while Billing is disabled for the tenant', function (): void {
        staffUser();
        $reservation = bookStay(['401']);
        DB::table('tenant_modules')->insert(['tenant_id' => tenant('sunrise')->id, 'module' => 'billing', 'enabled' => false]);

        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertOk()->assertDontSeeHtml('data-tab-hash="payments"');
    });
});

describe('guests', function (): void {
    it('adds a guest to a room, makes them primary and removes the other', function (): void {
        staffUser();
        $reservation = bookStay(['401', '402']);
        $ayesha = booking(fn (): Guest => Guest::factory()->create(['first_name' => 'Ayesha', 'last_name' => 'Siddique']));
        $itemId = $reservation->items->last()?->id;

        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests"), ['guest_id' => $ayesha->id, 'reservation_item_id' => $itemId])
            ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}#guests"))->assertSessionHas('success');

        $added = booking(fn (): ReservationGuest => ReservationGuest::query()->where('guest_id', $ayesha->id)->sole());
        expect($added->reservation_item_id)->toBe($itemId)->and($added->is_primary)->toBeFalse();

        put(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests/{$added->id}/primary"))->assertSessionHas('success');
        $rahim = booking(fn (): ReservationGuest => ReservationGuest::query()->where('guest_id', bookingIds()['guest'])->sole());
        delete(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests/{$rahim->id}"))->assertSessionHas('success');

        $changed = freshReservation($reservation->id);
        expect($changed->primary_guest_id)->toBe($ayesha->id)
            ->and($changed->guests->pluck('guest_id')->all())->toBe([$ayesha->id])
            ->and($changed->logs->pluck('action')->all())->toContain(ReservationLogAction::GuestAdded, ReservationLogAction::PrimaryGuestChanged, ReservationLogAction::GuestRemoved);
    });

    it('refuses blacklisted guests, guests already on the booking and removing the primary guest', function (): void {
        staffUser();
        $reservation = bookStay(['401']);
        $kamal = booking(fn (): Guest => Guest::factory()->create(['first_name' => 'Kamal', 'is_blacklisted' => true, 'blacklist_reason' => 'Unpaid bill']));
        $primary = booking(fn (): ReservationGuest => $reservation->guests()->sole());

        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests"), ['guest_id' => $kamal->id])->assertSessionHasErrors('guest_id');
        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests"), ['guest_id' => bookingIds()['guest']])->assertSessionHasErrors('guest_id');
        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests"), ['guest_id' => 999999])->assertSessionHasErrors('guest_id');
        delete(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests/{$primary->id}"))->assertSessionHas('error');

        expect(freshReservation($reservation->id)->guests)->toHaveCount(1);
    });

    it('is refused to staff without reservation.booking.update', function (): void {
        staffUser(DefaultRole::Accountant);
        $reservation = bookStay(['401']);

        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/guests"), ['guest_id' => bookingIds()['guest']])->assertForbidden();
    });

    it('moves bookings to the kept guest when two profiles are merged', function (): void {
        $reservation = bookStay(['401']);
        [$kept, $merged] = booking(fn (): array => [Guest::factory()->create(['first_name' => 'Md Rahim', 'last_name' => 'Uddin']), Guest::query()->findOrFail(bookingIds()['guest'])]);

        booking(fn () => MergeGuests::make()->handle($kept, $merged));

        $moved = freshReservation($reservation->id);
        expect($moved->primary_guest_id)->toBe($kept->id)
            ->and($moved->guests->pluck('guest_id')->all())->toBe([$kept->id])
            ->and($moved->guests->first()?->is_primary)->toBeTrue();
    });
});

describe('deposit', function (): void {
    it('changes the deposit within the policy', function (): void {
        $reservation = bookStay(['401']);

        booking(fn () => ChangeDeposit::make()->handle($reservation, '50'));

        $changed = freshReservation($reservation->id);
        expect([$changed->deposit_percent, $changed->deposit_required, $changed->deposit_override_by])->toBe(['50.00', '11385.00', null])
            ->and($changed->logs->first()?->action)->toBe(ReservationLogAction::DepositChanged);
    });

    it('needs the override permission below the policy minimum, and waiving the deposit confirms the booking', function (): void {
        Event::fake([ReservationConfirmed::class]);
        $user = staffUser(DefaultRole::FrontDeskAgent);
        $reservation = bookStay(['401']);

        put(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/deposit"), ['deposit_percent' => '0'])->assertSessionHasErrors('deposit_percent');
        expect(freshReservation($reservation->id)->deposit_percent)->toBe('30.00');

        $manager = staffUser(DefaultRole::FrontOfficeManager);
        put(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/deposit"), ['deposit_percent' => '0'])->assertSessionHas('success');

        $waived = freshReservation($reservation->id);
        expect($waived->status)->toBe(ReservationStatus::Confirmed)
            ->and([$waived->deposit_required, $waived->deposit_override_by])->toBe(['0.00', $manager->id])
            ->and($user->id)->not->toBe($manager->id);
        Event::assertDispatched(ReservationConfirmed::class);
    });

    it('refuses a deposit below the minimum in the action without the override', function (): void {
        booking(fn () => ChangeDeposit::make()->handle(bookStay(['401']), '10'));
    })->throws(DepositBelowMinimum::class);

    it('validates the percent', function (): void {
        staffUser();
        $reservation = bookStay(['401']);

        put(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/deposit"), ['deposit_percent' => '120'])->assertSessionHasErrors('deposit_percent');
    });
});

describe('cancel', function (): void {
    it('cancels with the policy fee for the days before arrival and releases the rooms', function (): void {
        Event::fake([ReservationCancelled::class]);
        $policy = booking(function (): CancellationPolicy {
            $policy = CancellationPolicy::factory()->create(['name' => 'Moderate']);
            CancellationPolicyRule::factory()->create(['cancellation_policy_id' => $policy->id, 'days_before_from' => 0, 'days_before_to' => 6, 'charge_type' => CancellationChargeType::PercentOfTotal, 'charge_value' => '50']);

            return $policy;
        });
        $reservation = bookStay(['401']);
        booking(fn () => freshReservation($reservation->id)->forceFill(['cancellation_policy_id' => $policy->id])->save());

        // Five days before arrival: 50% of 22,770.00.
        travelTo(CarbonImmutable::parse('2026-11-05 10:00', 'Asia/Dhaka'));
        staffUser();
        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/cancel"))->assertOk()->assertSeeInOrder([__('Cancellation fee'), '11,385.00']);
        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/cancel"), ['reason' => 'Flight cancelled'])
            ->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertSessionHas('success');

        $cancelled = freshReservation($reservation->id);
        expect($cancelled->status)->toBe(ReservationStatus::Cancelled)
            ->and([$cancelled->cancellation_fee, $cancelled->cancellation_reason, $cancelled->balance_due])->toBe(['11385.00', 'Flight cancelled', '11385.00'])
            ->and($cancelled->payment_status)->toBe(PaymentStatus::Unpaid)
            ->and(booking(fn (): int => InventoryLock::query()->where('reservation_id', $reservation->id)->count()))->toBe(0);
        Event::assertDispatched(ReservationCancelled::class, fn (ReservationCancelled $event): bool => $event->fee === '11385.00' && ! $event->expired);
    });

    it('needs a reason and the cancel permission, and cannot cancel twice', function (): void {
        staffUser();
        $reservation = bookStay(['401']);

        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/cancel"), ['reason' => ''])->assertSessionHasErrors('reason');
        post(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/cancel"), ['reason' => 'Guest asked']);
        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/cancel"))->assertRedirect(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}"))->assertSessionHas('error');

        staffUser(DefaultRole::HousekeepingSupervisor);
        get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/cancel"))->assertForbidden();
    });
});

it('refuses to delete a rate plan that upcoming bookings use', function (): void {
    bookStay(['401']);

    booking(fn () => DeleteRatePlan::make()->handle(RatePlan::query()->findOrFail(bookingIds()['plan'])));
})->throws(RatePlanInUse::class);
