<?php

/*
| A module may use another module's Contracts, DTOs, Enums and Events only
| (docs/ARCHITECTURE.md §4.3, §12.6). One test per ordered pair of modules.
*/

require_once __DIR__.'/helpers.php';

it('discovers the modules', function (): void {
    expect(moduleNames())->toContain('Core');
});

foreach (moduleNames() as $module) {
    foreach (moduleNames() as $other) {
        if ($module === $other) {
            continue;
        }

        arch("{$module} uses only the public API of {$other}", function () use ($module, $other): void {
            expect("Modules\\{$module}")
                ->not->toUse("Modules\\{$other}")
                ->ignoring([
                    "Modules\\{$other}\\Contracts",
                    "Modules\\{$other}\\DTOs",
                    "Modules\\{$other}\\Enums",
                    "Modules\\{$other}\\Events",
                ]);
        });
    }
}
