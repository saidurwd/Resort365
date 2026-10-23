<?php

/*
| Folio API (Step 8.1, read only): a booking's folios with their balances, and one folio with its lines, over /api/v1/billing.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Actions\PostCharge;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\Folio;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Billing\Services\FolioLedger;
use Modules\Core\Models\TaxCategory;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

uses(RefreshDatabase::class);

beforeEach(function (): void {
    bookingSetup(withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise'])));
    booking(fn () => app(DefaultChargeCodes::class)->ensure(TaxCategory::query()->where('code', 'ROOM')->value('id')));
    Notification::fake();
});

/** @param  list<string>|null  $abilities */
function faToken(?array $abilities = null): string
{
    $user = tenantUserAs(tenant('sunrise'), DefaultRole::FrontOfficeManager, ['email' => 'fom'.random_int(1, 999_999).'@sunrise.test']);
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => bookingIds()['property']]);

    return apiToken($user, $abilities);
}

it('lists a booking\'s folios and shows one with its lines', function (): void {
    $reservation = bookStay(['401']);
    $folio = booking(function () use ($reservation): Folio {
        $folio = app(FolioLedger::class)->guestFolio($reservation->id);
        PostCharge::make()->handle(new FolioCharge($reservation->id, (int) ChargeCode::query()->where('code', 'TRANSFER')->value('id'), '1000.00', description: 'Airport pickup'));

        return $folio;
    });
    $token = faToken();

    api($token)->getJson(tenantUrl('sunrise', "/api/v1/billing/reservations/{$reservation->id}/folios"))->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.folio_no', $folio->folio_no)->assertJsonPath('data.0.balance', '1265.00')->assertJsonMissingPath('data.0.lines');

    api($token)->getJson(tenantUrl('sunrise', "/api/v1/billing/folios/{$folio->id}"))->assertOk()
        ->assertJsonPath('data.type', 'guest')
        ->assertJsonPath('data.lines.0.description', 'Airport pickup')
        ->assertJsonPath('data.lines.0.total', '1265.00')
        ->assertJsonPath('data.lines.0.is_voided', false);
});

it('answers unknown bookings and folios with 404 and needs the folio ability', function (): void {
    $reservation = bookStay(['401']);
    $folio = booking(fn (): Folio => app(FolioLedger::class)->guestFolio($reservation->id));

    api(faToken())->getJson(tenantUrl('sunrise', '/api/v1/billing/reservations/999999/folios'))->assertNotFound()->assertJsonPath('code', 'not_found');
    api(faToken())->getJson(tenantUrl('sunrise', '/api/v1/billing/folios/999999'))->assertNotFound();
    api(faToken(['reservation.booking.view']))->getJson(tenantUrl('sunrise', "/api/v1/billing/folios/{$folio->id}"))->assertForbidden();
});
