<?php

/*
| Step 0.7 "Done when": 1,000 numbers generated concurrently contain no duplicates.
| Ten separate PHP processes take 100 numbers each from the same sequence, against the real
| test database (committed data, so no RefreshDatabase transaction here).
*/

use App\Actions\Tenancy\CreateTenant;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Process\Pool;
use Illuminate\Support\Facades\Process;

uses(DatabaseTruncation::class);

it('generates 1,000 numbers concurrently without duplicates or gaps', function (): void {
    // Created the normal way, so its document sequences exist (CreateDocumentSequences).
    $tenant = CreateTenant::make()->handle('concurrency', 'Concurrency Test');
    $connection = config('database.connections.mysql');

    $env = [
        'APP_ENV' => 'testing',
        'CACHE_STORE' => 'array',
        'QUEUE_CONNECTION' => 'sync',
        'SESSION_DRIVER' => 'array',
        'DB_CONNECTION' => 'mysql',
        'DB_HOST' => (string) $connection['host'],
        'DB_PORT' => (string) $connection['port'],
        'DB_DATABASE' => (string) $connection['database'],
        'DB_USERNAME' => (string) $connection['username'],
        'DB_PASSWORD' => (string) $connection['password'],
    ];

    try {
        $results = Process::pool(function (Pool $pool) use ($tenant, $env): void {
            foreach (range(1, 10) as $worker) {
                $pool->as((string) $worker)->env($env)->timeout(120)
                    ->command([PHP_BINARY, base_path('tests/Support/generate-document-numbers.php'), (string) $tenant->id, '100']);
            }
        })->start()->wait();

        $numbers = [];
        $report = [];

        foreach ($results as $worker => $result) {
            $lines = array_filter(explode(PHP_EOL, trim($result->output())));
            $report[] = "worker {$worker}: exit ".$result->exitCode().', '.count($lines).' numbers'.($result->errorOutput() !== '' ? ', stderr: '.mb_substr($result->errorOutput(), 0, 300) : '');
            array_push($numbers, ...$lines);
        }

        expect(count($numbers))->toBe(1000, implode(PHP_EOL, $report));

        $sequence = array_map(fn (string $number): int => (int) substr($number, strrpos($number, '-') + 1), $numbers);
        sort($sequence);

        expect($numbers)->toHaveCount(1000)
            ->and(array_unique($numbers))->toHaveCount(1000)
            ->and($sequence)->toBe(range(1, 1000))
            ->and($numbers[0])->toStartWith('RSV-2026-');
    } finally {
        $tenant->delete();
    }
});
