<?php

/*
| Coverage of Actions and Services per module (Step 7.6, ARCHITECTURE §13) from a Clover report:
|
|   php artisan test --coverage-clover=build/coverage.xml
|   php scripts/coverage-report.php build/coverage.xml [--min-file=scripts/coverage-floor.json]
|
| Prints statement coverage of app/Actions and app/Services for each module §13 names, and exits 1 when a module is below its floor
| (scripts/coverage-floor.json: module => percent). The target is 80%; the floor records where each module stands, so it can only rise.
*/

$modules = ['Reservation', 'Billing', 'Restaurant', 'Accounting', 'Inventory', 'Payroll'];
$report = $argv[1] ?? 'build/coverage.xml';
$floorFile = null;

foreach (array_slice($argv, 2) as $argument) {
    if (str_starts_with($argument, '--min-file=')) {
        $floorFile = substr($argument, strlen('--min-file='));
    }
}

if (! is_file($report)) {
    fwrite(STDERR, "No coverage report at {$report}.\n");
    exit(2);
}

$xml = simplexml_load_file($report);

if ($xml === false) {
    fwrite(STDERR, "{$report} is not a Clover report.\n");
    exit(2);
}

$totals = array_fill_keys($modules, ['statements' => 0, 'covered' => 0]);

foreach ($xml->xpath('//file') ?: [] as $file) {
    $path = str_replace('\\', '/', (string) $file['name']);

    if (preg_match('#/Modules/([^/]+)/app/(Actions|Services)/#', $path, $match) !== 1 || ! isset($totals[$match[1]])) {
        continue;
    }

    $metrics = $file->metrics;
    $totals[$match[1]]['statements'] += (int) $metrics['statements'];
    $totals[$match[1]]['covered'] += (int) $metrics['coveredstatements'];
}

$floor = $floorFile !== null && is_file($floorFile) ? (array) json_decode((string) file_get_contents($floorFile), true) : [];
$failed = false;

printf("%-12s %10s %10s %8s %8s\n", 'Module', 'Statements', 'Covered', 'Percent', 'Floor');

foreach ($totals as $module => $row) {
    $percent = $row['statements'] > 0 ? round($row['covered'] / $row['statements'] * 100, 1) : 0.0;
    $minimum = isset($floor[$module]) ? (float) $floor[$module] : null;
    $below = $minimum !== null && $percent < $minimum;
    $failed = $failed || $below;

    printf("%-12s %10d %10d %7.1f%% %8s%s\n", $module, $row['statements'], $row['covered'], $percent, $minimum !== null ? $minimum.'%' : '-', $below ? '  BELOW THE FLOOR' : '');
}

exit($failed ? 1 : 0);
