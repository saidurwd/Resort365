<?php

/*
| Child process for the document-number concurrency test.
| Usage: php tests/Support/generate-document-numbers.php <tenant id> <count>
| Prints one generated number per line. Connection settings come from the environment.
*/

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Modules\Core\Contracts\DocumentNumbers;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $tenantId, $count] = $argv;

$tenant = Tenant::query()->findOrFail((int) $tenantId);

try {
    app(TenantContext::class)->run($tenant, function () use ($count): void {
        $numbers = app(DocumentNumbers::class);

        for ($i = 0; $i < (int) $count; $i++) {
            echo $numbers->next('reservation', null, new DateTimeImmutable('2026-06-15')), PHP_EOL;
        }
    });
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage().PHP_EOL);
    exit(1);
}
