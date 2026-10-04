<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Models\InventoryLock;

/**
 * Inventory locks for DemoSeeder (Step 1.5), so availability shows their effect before bookings
 * exist: room 401 (Palm) blocked for the owner. Room 702's out-of-order week is a Housekeeping
 * block since Step 2.7 (DemoHousekeeping).
 */
final class DemoLocks
{
    public static function seed(Tenant $tenant, int $propertyId): void
    {
        app(TenantContext::class)->run($tenant, function () use ($propertyId): void {
            if (InventoryLock::query()->where('property_id', $propertyId)->exists()) {
                return;
            }

            $rooms = collect(app(InventoryCatalog::class)->rooms($propertyId))->keyBy(fn (RoomSummary $room): string => $room->number);
            $today = CarbonImmutable::now('Asia/Dhaka')->startOfDay();

            self::lock($propertyId, $rooms->get('401'), $today->addDays(3), $today->addDays(5), LockType::OwnerBlock, 'Owner\'s family visit');
        });
    }

    private static function lock(int $propertyId, ?RoomSummary $room, CarbonImmutable $from, CarbonImmutable $to, LockType $type, string $note): void
    {
        if (! $room instanceof RoomSummary) {
            return;
        }

        foreach (CarbonPeriod::create($from, $to) as $date) {
            InventoryLock::query()->create([
                'property_id' => $propertyId, 'room_id' => $room->id, 'stay_date' => $date->toDateString(), 'lock_type' => $type, 'note' => $note,
            ]);
        }
    }
}
