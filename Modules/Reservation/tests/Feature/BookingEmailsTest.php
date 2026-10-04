<?php

/*
| Booking notifications (Step 1.8). "Done when": the guest receives the correct email at each
| stage — received with the deposit and due time, confirmed with the voucher, cancelled with the
| fee, released when the deposit was not paid — using the tenant's own wording when it has one.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Modules\Core\Models\NotificationTemplate;
use Modules\Guest\Models\Guest;
use Modules\Reservation\Actions\ApplyPayment;
use Modules\Reservation\Actions\CancelReservation;
use Modules\Reservation\Enums\ReservationLogAction;
use Modules\Reservation\Jobs\ExpireTentativeHolds;
use Modules\Reservation\Notifications\GuestMessage;
use Modules\Reservation\Notifications\HoldExpiredNotice;

use function Pest\Laravel\get;
use function Pest\Laravel\travel;

require_once __DIR__.'/../Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    Notification::fake();
});

/**
 * @return list<array{subject: string, body: string, attachments: list<string>}>
 */
function guestEmails(string $address = 'rahim@example.com'): array
{
    $sent = [];

    Notification::assertSentOnDemand(GuestMessage::class, function (GuestMessage $message, array $channels, AnonymousNotifiable $notifiable) use ($address, &$sent): bool {
        if (array_key_exists($address, (array) ($notifiable->routes['mail'] ?? []))) {
            $sent[] = ['subject' => $message->subject, 'body' => $message->body, 'attachments' => array_keys($message->attachments)];
        }

        return true;
    });

    return $sent;
}

it('emails the guest when the booking is received, with the deposit and its due time', function (): void {
    $reservation = bookStay(['401']);

    $emails = guestEmails();
    expect($emails)->toHaveCount(1)
        ->and($emails[0]['subject'])->toBe("Your booking {$reservation->code} at Sunrise Cox's Bazar")
        ->and($emails[0]['body'])->toContain('Rahim Uddin,')->toContain('BDT 6,831.00')
        ->toContain($reservation->deposit_due_at?->setTimezone('Asia/Dhaka')->format('d M Y H:i'))
        ->toContain('Pay by bank transfer')
        ->and($emails[0]['attachments'])->toBe([])
        ->and(freshReservation($reservation->id)->logs->pluck('action')->all())->toContain(ReservationLogAction::EmailSent);
});

it('emails the confirmation with the voucher once the deposit is paid', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => ApplyPayment::make()->handle($reservation->id, '6831.00', '6831.00'));

    $confirmed = guestEmails()[1] ?? null;
    expect($confirmed['subject'] ?? null)->toBe("Booking {$reservation->code} confirmed")
        ->and($confirmed['body'] ?? '')->toContain('Paid so far: BDT 6,831.00')->toContain('Balance: BDT 15,939.00')->toContain('from 14:00')
        ->and($confirmed['attachments'] ?? [])->toBe([$reservation->code.'.pdf']);
});

it('sends only the confirmation when no deposit is due', function (): void {
    $reservation = bookStay(['401'], overrides: ['depositPercent' => '0', 'allowDepositOverride' => true]);

    expect(array_column(guestEmails(), 'subject'))->toBe(["Booking {$reservation->code} confirmed"]);
});

it('emails the cancellation with the fee and refund', function (): void {
    $reservation = bookStay(['401']);
    booking(fn () => CancelReservation::make()->handle($reservation, 'Guest asked'));

    $cancelled = guestEmails()[1] ?? null;
    expect($cancelled['subject'] ?? null)->toBe("Booking {$reservation->code} cancelled")
        ->and($cancelled['body'] ?? '')->toContain('Cancellation fee: BDT 0.00')->toContain('Refund due to you: BDT 0.00');
});

it('tells the guest and the staff member when an unpaid hold is released', function (): void {
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::FrontDeskAgent);
    $reservation = bookStay(['401'], overrides: ['createdBy' => $user->id]);

    travel(31)->minutes();
    ExpireTentativeHolds::dispatchSync();

    expect(guestEmails()[1]['subject'] ?? null)->toBe("Booking {$reservation->code} released");
    Notification::assertSentTo($user, HoldExpiredNotice::class, fn (HoldExpiredNotice $notice): bool => $notice->code === $reservation->code
        && booking(fn (): string => $notice->toArray($user)['title']) === "Booking {$reservation->code} released");
});

it('uses the tenant\'s own wording of a template', function (): void {
    booking(fn () => NotificationTemplate::factory()->create(['key' => 'reservation.booking_created', 'channel' => 'mail', 'locale' => 'en',
        'subject' => 'Thanks {guest}!', 'body' => "Hi {guest},\nPlease send {currency} {deposit} for {code}."]));

    $reservation = bookStay(['401']);

    $email = guestEmails()[0];
    expect($email['subject'])->toStartWith('Thanks ')->toEndWith('Rahim Uddin!')
        ->and($email['body'])->toEndWith("Rahim Uddin,\nPlease send BDT 6,831.00 for {$reservation->code}.");
});

it('notes in the history when the guest has no email address', function (): void {
    booking(fn () => Guest::query()->whereKey(bookingIds()['guest'])->update(['email' => null]));

    $reservation = bookStay(['401']);

    Notification::assertNothingSent();
    expect(freshReservation($reservation->id)->logs->firstWhere('action', ReservationLogAction::EmailSent)?->description)->toContain('no email address');
});

it('renders the email with the first template line as greeting and the voucher attached', function (): void {
    $mail = new GuestMessage('Booking confirmed', "Dear Rahim,\nLine one.\n\nLine two.", "Sunrise Cox's Bazar", ['RSV-1.pdf' => '%PDF-1.4'])->toMail(new AnonymousNotifiable);

    expect($mail->greeting)->toBe('Dear Rahim,')
        ->and($mail->introLines)->toBe(['Line one.', 'Line two.'])
        ->and($mail->rawAttachments[0]['name'] ?? null)->toBe('RSV-1.pdf');
});

it('downloads the voucher of a confirmed booking only', function (): void {
    staffUser();
    $reservation = bookStay(['401']);

    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/voucher"))->assertRedirect()->assertSessionHas('error');

    booking(fn () => ApplyPayment::make()->handle($reservation->id, '6831.00', '6831.00'));
    $response = get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/voucher"))->assertOk()->assertHeader('content-type', 'application/pdf');
    expect((string) $response->getContent())->toStartWith('%PDF');

    staffUser(DefaultRole::HousekeepingSupervisor);
    get(tenantUrl('sunrise', "/reservation/bookings/{$reservation->id}/voucher"))->assertForbidden();
});
