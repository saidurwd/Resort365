<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/bootstrap/app.php',
        __DIR__.'/config',
        __DIR__.'/database',
        __DIR__.'/routes',
        __DIR__.'/tests',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(deadCode: true, codeQuality: true, typeDeclarations: true, earlyReturn: true)
    ->withSets([LaravelLevelSetList::UP_TO_LARAVEL_130])
    ->withImportNames(removeUnusedImports: true)
    ->withSkip([
        // Laravel's stubs (artisan make:*) don't declare strict types; keep generated code lint-clean.
        SafeDeclareStrictTypesRector::class,
    ]);
