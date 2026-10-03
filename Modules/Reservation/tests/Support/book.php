<?php

/*
| Child process for the booking concurrency test.
| Usage: php book.php <tenant id> <property id> <guest id> <plan id> <check-in> <check-out> <type:id>[,<type:id>…]
| Prints "BOOKED <code>" or "TAKEN"; connection settings come from the environment.
*/

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Modules\Reservation\Actions\CreateReservation;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Exceptions\RoomNoLongerAvailable;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $tenantId, $propertyId, $guestId, $planId, $checkIn, $checkOut, $units] = $argv;

$items = array_map(function (string $unit) use ($planId): BookingItem {
    [$type, $id] = explode(':', $unit);

    return new BookingItem(ItemType::from($type), (int) $id, (int) $planId, 2);
}, explode(',', $units));

try {
    $code = app(TenantContext::class)->run(Tenant::query()->findOrFail((int) $tenantId), fn (): string => CreateReservation::make()->handle(new NewReservation(
        (int) $propertyId, CarbonImmutable::parse($checkIn), CarbonImmutable::parse($checkOut), $items, (int) $guestId,
    ))->code);
    echo 'BOOKED ', $code, PHP_EOL;
} catch (RoomNoLongerAvailable) {
    echo 'TAKEN', PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage().PHP_EOL);
    exit(1);
}
