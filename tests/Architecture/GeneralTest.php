<?php

require_once __DIR__.'/helpers.php';

arch('App has no debugging calls', function (): void {
    expect('App')->not->toUse(debugFunctions());
});

arch('App controllers do not query the database directly', function (): void {
    expect('App\Http\Controllers')->not->toUse(databaseAccess());
    expect(filesUsingDbAlias('app/Http/Controllers'))->toBeEmpty();
});

foreach (moduleNames() as $module) {
    arch("{$module} has no debugging calls", function () use ($module): void {
        expect("Modules\\{$module}")->not->toUse(debugFunctions());
    });

    arch("{$module} controllers do not query the database directly", function () use ($module): void {
        expect("Modules\\{$module}\\Http\\Controllers")->not->toUse(databaseAccess());
        expect(filesUsingDbAlias("Modules/{$module}/app/Http/Controllers"))->toBeEmpty();
    });
}
