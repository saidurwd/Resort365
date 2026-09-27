<?php

use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;

function sampleRegistry(): PermissionRegistry
{
    $registry = new PermissionRegistry;
    $registry->register('Front office', [
        new PermissionDefinition('frontoffice.desk.view', 'View front desk', [DefaultRole::FrontDeskAgent, DefaultRole::FrontOfficeManager]),
        new PermissionDefinition('frontoffice.checkin.create', 'Check guests in', [DefaultRole::FrontDeskAgent]),
    ]);
    $registry->register('Accounting', [
        new PermissionDefinition('accounting.journal.view', 'View journals', [DefaultRole::Accountant]),
        new PermissionDefinition('accounting.journal.post', 'Post journals', [DefaultRole::Accountant]),
    ]);

    return $registry;
}

it('lists permissions sorted by name', function (): void {
    expect(sampleRegistry()->names())->toBe([
        'accounting.journal.post',
        'accounting.journal.view',
        'frontoffice.checkin.create',
        'frontoffice.desk.view',
    ]);
});

it('groups permissions by module for the role editor', function (): void {
    $grouped = sampleRegistry()->grouped();

    expect(array_keys($grouped))->toBe(['accounting', 'frontoffice'])
        ->and($grouped['frontoffice']['label'])->toBe('Front office')
        ->and(array_map(fn (PermissionDefinition $p): string => $p->name, $grouped['accounting']['permissions']))->toBe(['accounting.journal.post', 'accounting.journal.view']);
});

it('gives each default role its permissions', function (): void {
    $registry = sampleRegistry();

    expect($registry->namesFor(DefaultRole::FrontDeskAgent))->toBe(['frontoffice.checkin.create', 'frontoffice.desk.view'])
        ->and($registry->namesFor(DefaultRole::Accountant))->toBe(['accounting.journal.post', 'accounting.journal.view'])
        ->and($registry->namesFor(DefaultRole::TenantOwner))->toBe($registry->names())
        ->and($registry->namesFor(DefaultRole::Auditor))->toBe(['accounting.journal.view', 'frontoffice.desk.view'])
        ->and($registry->namesFor(DefaultRole::Chef))->toBe([]);
});

it('rejects duplicate and badly named permissions', function (): void {
    $registry = sampleRegistry();

    expect(fn () => $registry->register('Again', [new PermissionDefinition('accounting.journal.view', 'Dup')]))->toThrow(InvalidArgumentException::class)
        ->and(fn (): PermissionDefinition => new PermissionDefinition('Accounting.Journal', 'Bad'))->toThrow(InvalidArgumentException::class);
});
