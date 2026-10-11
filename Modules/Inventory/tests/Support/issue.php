<?php

/*
| Child process for the stock concurrency test.
| Usage: php issue.php <tenant id> <store id> <item id> <quantity>
| Prints "ISSUED <quantity after>" or "SHORT"; connection settings come from the environment.
*/

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Modules\Inventory\Contracts\StockMovementContract;
use Modules\Inventory\DTOs\NewStockMovement;
use Modules\Inventory\Enums\MovementType;
use Modules\Inventory\Exceptions\InsufficientStock;

require __DIR__.'/../../../../vendor/autoload.php';

$app = require __DIR__.'/../../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $tenantId, $storeId, $itemId, $quantity] = $argv;

try {
    $after = app(TenantContext::class)->run(Tenant::query()->findOrFail((int) $tenantId), fn (): string => app(StockMovementContract::class)
        ->record(new NewStockMovement(MovementType::Issue, (int) $storeId, (int) $itemId, $quantity))->quantityAfter);
    echo 'ISSUED ', $after, PHP_EOL;
} catch (InsufficientStock) {
    echo 'SHORT', PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage().PHP_EOL);
    exit(1);
}
