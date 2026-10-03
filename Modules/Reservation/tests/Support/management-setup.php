<?php

/*
| Helpers for the reservation management and payment tests (plain functions, loaded with
| require_once), on top of booking-setup.php: tenant "sunrise", property CXB, rooms 401/402 and
| villas C07/C08 at 6,000 / 15,000 a night with SC 10% + VAT 15%, deposit policy Standard 30%
| (minimum 20%, due in 30 minutes, auto-cancel).
*/

use App\Http\Middleware\SetCurrentProperty;
use App\Support\Authorization\DefaultRole;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\IAM\Models\User;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Models\Reservation;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withSession;

require_once __DIR__.'/booking-setup.php';

/**
 * Books rooms (by number) or whole cottages (by code) for two adults each through CreateReservation.
 *
 * @param  list<string>  $units
 * @param  array<string, mixed>  $overrides  NewReservation fields
 */
function bookStay(array $units = ['401'], string $checkIn = '2026-11-10', string $checkOut = '2026-11-13', array $overrides = [], string $slug = 'sunrise'): Reservation
{
    $ids = bookingIds($slug);
    $items = array_map(fn (string $unit): BookingItem => isset($ids['cottages'][$unit])
        ? new BookingItem(ItemType::Cottage, $ids['cottages'][$unit], $ids['plan'], 2)
        : new BookingItem(ItemType::Room, $ids['rooms'][$unit], $ids['plan'], 2), $units);

    return booking(fn (): Reservation => CreateReservation::make()->handle(NewReservation::from([
        'propertyId' => $ids['property'], 'checkIn' => CarbonImmutable::parse($checkIn), 'checkOut' => CarbonImmutable::parse($checkOut),
        'items' => $items, 'primaryGuestId' => $ids['guest'], ...$overrides,
    ])), $slug);
}

/**
 * Signs in a staff member of the role, with access to CXB as the current property.
 */
function staffUser(DefaultRole $role = DefaultRole::FrontDeskAgent, string $slug = 'sunrise'): User
{
    $user = tenantUserAs(tenant($slug), $role);
    $propertyId = bookingIds($slug)['property'];
    DB::table('property_user')->insert(['tenant_id' => $user->tenant_id, 'user_id' => $user->id, 'property_id' => $propertyId]);
    withSession([SetCurrentProperty::SESSION_KEY => $propertyId]);
    actingAs($user);

    return $user;
}

/**
 * The reservation as stored now (outside any user's property restriction).
 */
function freshReservation(int $id, string $slug = 'sunrise'): Reservation
{
    return booking(fn (): Reservation => Reservation::query()->with(['items', 'guests', 'logs'])->findOrFail($id), $slug);
}
