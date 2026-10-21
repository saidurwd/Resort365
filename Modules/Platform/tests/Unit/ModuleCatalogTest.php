<?php

/*
| The modules a plan can include and what each needs (Step 7.3). Pure.
*/

use Modules\Platform\Services\ModuleCatalog;

it('adds what a module needs, in catalogue order', function (): void {
    expect(ModuleCatalog::withDependencies(['restaurant']))->toBe(['property', 'guest', 'rates', 'reservation', 'billing', 'restaurant'])
        ->and(ModuleCatalog::withDependencies(['payroll']))->toBe(['property', 'hr', 'payroll'])
        ->and(ModuleCatalog::withDependencies(['frontoffice']))->toBe(['property', 'guest', 'rates', 'reservation', 'billing', 'frontoffice'])
        ->and(ModuleCatalog::withDependencies(['procurement']))->toBe(['property', 'inventory', 'procurement']);
});

it('drops unknown modules and repeats, and leaves independent ones alone', function (): void {
    expect(ModuleCatalog::withDependencies(['accounting', 'accounting', 'nonsense', 'core']))->toBe(['accounting'])->and(ModuleCatalog::withDependencies([]))->toBe([]);
});

it('keeps every module reachable from the catalogue and every need inside it', function (): void {
    foreach (ModuleCatalog::MODULES as $alias => [$label, $needs]) {
        expect($label)->not->toBe('');

        foreach ($needs as $need) {
            expect(ModuleCatalog::MODULES)->toHaveKey($need)->and(ModuleCatalog::needs($need))->not->toContain($alias);
        }
    }

    expect(ModuleCatalog::withDependencies(ModuleCatalog::aliases()))->toBe(ModuleCatalog::aliases());
});
