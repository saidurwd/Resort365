<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Modules\Property\Models\Property;
use Modules\Property\Models\Room;
use Modules\Reservation\Models\Reservation;
use Modules\Reservation\Models\ReservationItem;
use stdClass;

/**
 * Reports demo (Step 7.1): a year of history for the dashboard and the rooms and guest reports. The night audit's daily statistics
 * for the last 365 days (busy from November to February, quiet in the monsoon, fuller on weekends) and past bookings with the
 * sources, lengths of stay, cancellations, no-shows, nationalities, repeat guests and companies the reports show. The history is
 * only history: it has no inventory locks or folios.
 */
final class DemoReports
{
    /** Bookings of the history, by property. */
    private const int BOOKINGS = 600;

    private const array SOURCES = ['front_desk' => 25, 'phone' => 15, 'email' => 12, 'walk_in' => 10, 'travel_agent' => 14, 'corporate' => 12, 'online' => 12];

    /** Nights of a stay and how often. */
    private const array NIGHTS = [1 => 20, 2 => 30, 3 => 22, 4 => 12, 5 => 7, 7 => 6, 10 => 3];

    private const array NATIONALITIES = ['GB', 'US', 'IN', 'DE', 'CN', 'AU', 'FR', 'JP', 'SG', 'AE'];

    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function (Tenant $tenant) use ($propertyId): void {
            if (DB::table('daily_statistics')->where('tenant_id', $tenant->id)->where('property_id', $propertyId)->exists()) {
                return;
            }

            mt_srand($propertyId * 7919);
            $property = Property::query()->findOrFail($propertyId);
            $today = CarbonImmutable::parse($property->business_date->toDateString());
            self::statistics($tenant->id, $property, $today);
            self::history($tenant->id, $property, $today);
        });
    }

    private static function statistics(int $tenantId, Property $property, CarbonImmutable $today): void
    {
        $rooms = Room::query()->where('property_id', $property->id)->where('is_active', true)->count();
        $rows = [];
        $now = now();

        for ($day = $today->subDays(365); $day->lt($today); $day = $day->addDay()) {
            [$base, $rate] = self::season($day);
            $weekend = in_array($day->dayOfWeek, [5, 6], true) ? 0.12 : 0.0;
            $blocked = mt_rand(0, 9) === 0 ? 1 : 0;
            $available = max(1, $rooms - $blocked);
            $occupied = (int) round($available * max(0.08, min(1.0, $base + $weekend + (mt_rand(-10, 10) / 100))));
            $adr = round($rate * (mt_rand(92, 108) / 100), 2);
            $revenue = round($occupied * $adr, 2);
            $covers = (int) round($occupied * 2.1 * (mt_rand(70, 95) / 100));
            $fnb = round($covers * mt_rand(650, 950), 2);
            $rows[] = [
                'tenant_id' => $tenantId, 'property_id' => $property->id, 'business_date' => $day->toDateString(), 'rooms_total' => $rooms, 'rooms_out_of_order' => $blocked, 'rooms_blocked' => 0, 'rooms_available' => $available,
                'rooms_occupied' => $occupied, 'occupancy_percent' => round($occupied * 100 / $available, 2), 'adr' => $occupied > 0 ? $adr : 0, 'revpar' => round($revenue / $available, 2), 'room_revenue' => $revenue, 'package_meal_revenue' => 0,
                'room_tax' => round($revenue * 0.265, 2), 'charges_total' => round($revenue * 1.265 + $fnb, 2), 'received_total' => round(($revenue * 1.265 + $fnb) * (mt_rand(85, 100) / 100), 2), 'refunded_total' => mt_rand(0, 12) === 0 ? 2500 : 0,
                'adults' => (int) round($occupied * 1.9), 'children' => (int) round($occupied * 0.3), 'arrivals' => (int) round($occupied / 2.4), 'departures' => (int) round($occupied / 2.4), 'no_shows' => mt_rand(0, 25) === 0 ? 1 : 0,
                'fnb_covers' => $covers, 'fnb_sales' => $fnb, 'takings' => json_encode(new stdClass), 'created_at' => $now, 'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('daily_statistics')->insert($chunk);
        }
    }

    private static function history(int $tenantId, Property $property, CarbonImmutable $today): void
    {
        $rooms = Room::query()->where('property_id', $property->id)->where('is_active', true)->get(['id', 'cottage_id', 'room_type_id']);
        $ratePlan = (int) DB::table('rate_plans')->where('tenant_id', $tenantId)->where('property_id', $property->id)->orderBy('id')->value('id');
        $guestIds = DB::table('guests')->where('tenant_id', $tenantId)->orderBy('id')->limit(400)->pluck('id')->all();
        $companyIds = DB::table('companies')->where('tenant_id', $tenantId)->pluck('id')->all();
        $agentIds = DB::table('travel_agents')->where('tenant_id', $tenantId)->pluck('id')->all();

        if ($rooms->isEmpty() || $ratePlan === 0 || $guestIds === []) {
            return;
        }

        // Give a good part of the guests a foreign nationality, so the nationality mix has something to show.
        foreach ($guestIds as $index => $id) {
            if ($index % 3 === 0) {
                DB::table('guests')->where('id', $id)->whereNull('nationality_code')->update(['nationality_code' => self::NATIONALITIES[$index % count(self::NATIONALITIES)]]);
            }
        }

        $count = $property->code === 'CXB' ? self::BOOKINGS : self::BOOKINGS / 4;

        for ($n = 1; $n <= $count; $n++) {
            $nights = self::pick(self::NIGHTS);
            $checkIn = $today->subDays(mt_rand($nights + 1, 364));
            $checkOut = $checkIn->addDays($nights);
            $source = (string) self::pick(self::SOURCES);
            $roomsBooked = mt_rand(1, 100) <= 85 ? 1 : mt_rand(2, 3);
            $lead = (int) round((mt_rand(1, 1000) / 1000) ** 1.8 * 120);
            $createdAt = $checkIn->subDays($lead)->setTime(10, 0)->utc();
            $roll = mt_rand(1, 100);
            $status = $roll <= 12 ? 'cancelled' : ($roll <= 15 ? 'no_show' : 'checked_out');
            [, $rate] = self::season($checkIn);
            $subtotal = round($nights * $roomsBooked * $rate * (mt_rand(90, 110) / 100), 2);
            $total = round($subtotal * 1.265, 2);
            $cancelled = $status === 'cancelled';
            $guest = $guestIds[(int) floor((mt_rand() / mt_getrandmax()) ** 2.2 * count($guestIds))];
            $reservation = Reservation::unguarded(fn (): Reservation => Reservation::query()->create([
                'property_id' => $property->id, 'code' => 'RSV-H'.$property->id.'-'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), 'status' => $status, 'payment_status' => $cancelled ? 'unpaid' : 'fully_paid', 'source' => $source,
                'primary_guest_id' => $guest, 'company_id' => $source === 'corporate' && $companyIds !== [] ? $companyIds[array_rand($companyIds)] : null,
                'travel_agent_id' => $source === 'travel_agent' && $agentIds !== [] ? $agentIds[array_rand($agentIds)] : null, 'rate_plan_id' => $ratePlan, 'check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString(),
                'adults' => 2, 'children' => mt_rand(0, 4) === 0 ? 1 : 0, 'currency_code' => $property->currency_code, 'subtotal' => $subtotal, 'tax_total' => round($total - $subtotal, 2), 'grand_total' => $total,
                'deposit_percent' => 30, 'deposit_required' => round($total * 0.3, 2), 'amount_paid' => $cancelled ? 0 : $total, 'balance_due' => 0,
                'cancelled_at' => $cancelled ? $checkIn->subDays(mt_rand(0, max(0, min($lead, 30))))->setTime(14, 0)->utc() : null, 'cancellation_reason' => $cancelled ? 'Change of plans' : null,
                'cancellation_fee' => $cancelled && mt_rand(1, 3) === 1 ? round($subtotal * 0.3, 2) : 0, 'created_at' => $createdAt, 'updated_at' => $createdAt,
            ]));

            for ($r = 0; $r < $roomsBooked; $r++) {
                $room = $rooms->random();
                ReservationItem::unguarded(fn () => ReservationItem::query()->create([
                    'property_id' => $property->id, 'reservation_id' => $reservation->id, 'item_type' => 'room', 'cottage_id' => $room->cottage_id, 'room_id' => $room->id, 'room_type_id' => $room->room_type_id, 'rate_plan_id' => $ratePlan,
                    'check_in' => $checkIn->toDateString(), 'check_out' => $checkOut->toDateString(), 'adults' => 2, 'children' => 0, 'status' => $status, 'subtotal' => round($subtotal / $roomsBooked, 2),
                    'tax' => round(($total - $subtotal) / $roomsBooked, 2), 'total' => round($total / $roomsBooked, 2), 'created_at' => $createdAt, 'updated_at' => $createdAt,
                ]));
            }
        }
    }

    /**
     * The base occupancy and average rate of a day's season: busy November to February, quiet May to September.
     *
     * @return array{0: float, 1: float}
     */
    private static function season(CarbonImmutable $day): array
    {
        return match (true) {
            in_array($day->month, [11, 12, 1, 2], true) => [0.80, 9500.0],
            in_array($day->month, [3, 4, 10], true) => [0.60, 7800.0],
            default => [0.36, 6200.0],
        };
    }

    /**
     * A key chosen by weight.
     *
     * @param  array<int|string, int>  $weights
     */
    private static function pick(array $weights): int|string
    {
        $roll = mt_rand(1, array_sum($weights));

        foreach ($weights as $key => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                return $key;
            }
        }

        return array_key_first($weights);
    }
}
