<?php

/*
| Shared setup for the booking tests (plain functions, loaded with require_once):
| tenant "sunrise", property CXB (Asia/Dhaka, check-in 14:00) with
| - room type Deluxe King (base 2, max 2 + 1 child, 3), 6,000 a night, extras 1,500 / 750;
| - cottage type Family Villa (max 11), 15,000 a night whole;
| - villas C07 (rooms 701–703) and C08 (rooms 801–803), sold whole or by the room;
| - cottage C04 (rooms 401, 402), sold by the room only;
| - plan Room Only with Room taxes (SC 10% + VAT 15% compound);
| - deposit policy Standard: 30%, minimum 20%, due in 30 minutes, auto-cancel.
*/

use App\Models\Tenant;
use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\DocumentNumbers;
use Modules\Core\Models\Tax;
use Modules\Core\Models\TaxCategory;
use Modules\Guest\Models\Guest;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\CottageType;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Property\Models\RoomType;
use Modules\Rates\Actions\SaveRateSheet;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\RatePlan;

function bookingSetup(Tenant $tenant): void
{
    app(TenantContext::class)->run($tenant, function (): void {
        $cxb = Property::factory()->create(['code' => 'CXB', 'name' => "Sunrise Cox's Bazar", 'timezone' => 'Asia/Dhaka', 'check_in_time' => '14:00']);
        $king = RoomType::factory()->create(['property_id' => $cxb->id, 'code' => 'DK', 'name' => 'Deluxe King', 'base_occupancy' => 2, 'max_adults' => 2, 'max_children' => 1, 'max_occupancy' => 3]);
        $villa = CottageType::factory()->create(['property_id' => $cxb->id, 'code' => 'FV', 'name' => 'Family Villa', 'max_occupancy' => 11]);

        foreach ([['C07', 'Lagoon Villa', '70', BookingMode::Both, 3], ['C08', 'Sunset Villa', '80', BookingMode::Both, 3], ['C04', 'Palm', '40', BookingMode::RoomsOnly, 2]] as [$code, $name, $prefix, $mode, $rooms]) {
            $cottage = Cottage::factory()->create(['property_id' => $cxb->id, 'cottage_type_id' => $villa->id, 'code' => $code, 'name' => $name, 'booking_mode' => $mode]);

            foreach (range(1, $rooms) as $n) {
                Room::factory()->create(['property_id' => $cxb->id, 'cottage_id' => $cottage->id, 'room_type_id' => $king->id, 'number' => $prefix.$n]);
            }
        }

        $sc = Tax::factory()->create(['code' => 'SC', 'rate' => '10', 'sort_order' => 10]);
        $vat = Tax::factory()->compound()->create(['code' => 'VAT', 'rate' => '15', 'sort_order' => 20]);
        $room = TaxCategory::factory()->create(['code' => 'ROOM']);
        $room->taxes()->attach([$sc->id => ['tenant_id' => $room->tenant_id], $vat->id => ['tenant_id' => $room->tenant_id]]);

        DepositPolicy::factory()->create(['property_id' => $cxb->id, 'name' => 'Standard', 'default_percent' => '30', 'min_percent' => '20', 'is_default' => true]);
        $plan = RatePlan::factory()->create(['property_id' => $cxb->id, 'code' => 'RO', 'name' => 'Room Only', 'tax_category_id' => $room->id,
            'meal_adult_amount' => '0', 'meal_child_amount' => '0', 'valid_from' => null, 'valid_to' => null]);
        SaveRateSheet::make()->handle($plan, null, [
            'room_type:'.$king->id => ['every' => ['amount' => '6000', 'extra_adult_amount' => '1500', 'extra_child_amount' => '750']],
            'cottage_type:'.$villa->id => ['every' => ['amount' => '15000']],
        ], 48);

        Guest::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'phone' => '+8801711000001', 'email' => 'rahim@example.com']);
        app(DocumentNumbers::class)->ensure('reservation', $cxb->id);
    });
}

/**
 * @template T
 *
 * @param  Closure(): T  $callback
 * @return T
 */
function booking(Closure $callback, string $slug = 'sunrise'): mixed
{
    app(PropertyContext::class)->clear();

    return app(TenantContext::class)->run(tenant($slug), $callback);
}

/**
 * Ids for the setup: property, plan, guest, cottages by code, rooms by number.
 *
 * @return array{property: int, plan: int, guest: int, cottages: array<int|string, int>, rooms: array<int|string, int>}
 */
function bookingIds(string $slug = 'sunrise'): array
{
    return booking(fn (): array => [
        'property' => Property::query()->where('code', 'CXB')->value('id'),
        'plan' => RatePlan::query()->where('code', 'RO')->value('id'),
        'guest' => Guest::query()->where('first_name', 'Rahim')->value('id'),
        'cottages' => Cottage::query()->pluck('id', 'code')->all(),
        'rooms' => Room::query()->pluck('id', 'number')->all(),
    ], $slug);
}
