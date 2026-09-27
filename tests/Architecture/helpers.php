<?php

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\DB;

/*
| Architecture tests are generated per module so every module is covered
| without editing the tests. Always pass a single namespace to expect():
| with an array of namespaces, Pest's negative expectations (not->toUse)
| do not check every namespace.
|
| Pest only sees dependencies that exist while the tests run, so it misses
| Laravel's global facade aliases (`\DB::`, `use DB;`) and functions that
| are not installed (e.g. `ray()`, which Larastan reports instead).
*/

/**
 * Names of every module under Modules/, enabled or not.
 *
 * @return list<string>
 */
function moduleNames(): array
{
    $paths = glob(dirname(__DIR__, 2).'/Modules/*', GLOB_ONLYDIR) ?: [];

    return array_map(basename(...), $paths);
}

/**
 * Functions that must never be committed.
 *
 * @return list<string>
 */
function debugFunctions(): array
{
    return ['dd', 'ddd', 'dump', 'ray', 'var_dump'];
}

/**
 * Database access that controllers must leave to Actions and Services.
 *
 * @return list<string>
 */
function databaseAccess(): array
{
    return [
        DB::class,
        DatabaseManager::class,
        ConnectionInterface::class,
    ];
}

/**
 * PHP files under the given directory (relative to the project root) that use
 * the global `DB` facade alias, which Pest's dependency analysis cannot see.
 *
 * @return list<string>
 */
function filesUsingDbAlias(string $directory): array
{
    $root = dirname(__DIR__, 2);
    $path = $root.'/'.$directory;

    if (! is_dir($path)) {
        return [];
    }

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));
    $offenders = [];

    foreach ($files as $file) {
        if (! $file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getPathname());

        if (preg_match('/\\\\DB::|^use\s+\\\\?DB\s*;/m', $source) === 1) {
            $offenders[] = substr($file->getPathname(), strlen($root) + 1);
        }
    }

    return $offenders;
}
