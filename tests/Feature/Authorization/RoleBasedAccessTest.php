<?php

/*
| Step 0.6 "Done when": a Front Desk user sees a different sidebar from the Accountant, and a
| direct URL to a forbidden page returns 403. Front Office and Accounting don't exist yet, so this
| test registers stand-in menu items and permissions the way those modules will.
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistry::class)->register('Front office', [
        new PermissionDefinition('frontoffice.desk.view', 'View front desk', [DefaultRole::FrontDeskAgent]),
    ]);
    app(PermissionRegistry::class)->register('Accounting', [
        new PermissionDefinition('accounting.journal.view', 'View journals', [DefaultRole::Accountant]),
    ]);

    Route::domain('{tenant}.'.config('tenancy.central_domain'))->middleware(['web', 'tenant', 'auth'])->group(function (): void {
        Route::get('/_front-desk', fn (): string => 'desk')->middleware('can:frontoffice.desk.view')->name('test.front-desk');
        Route::get('/_journals', fn (): string => 'journals')->middleware('can:accounting.journal.view')->name('test.journals');
    });
    Route::getRoutes()->refreshNameLookups();

    $menu = app(MenuRegistry::class);
    $menu->group('frontoffice', 'Front Office', 'bi-door-open', order: 100);
    $menu->add(new MenuItem('frontoffice.desk', 'Front Desk', route: 'test.front-desk', parent: 'frontoffice', permission: 'frontoffice.desk.view'));
    $menu->group('accounting', 'Accounting', 'bi-journal', order: 400);
    $menu->add(new MenuItem('accounting.journals', 'Journal Entries', route: 'test.journals', parent: 'accounting', permission: 'accounting.journal.view'));

    withDefaultRoles(Tenant::factory()->create(['slug' => 'sunrise']));
});

/**
 * Sidebar labels on the dashboard for a user with the given default role.
 *
 * @return list<string>
 */
function sidebarOf(DefaultRole $role): array
{
    actingAs(tenantUserAs(tenant('sunrise'), $role));
    $html = get(tenantUrl('sunrise', '/dashboard'))->assertOk()->getContent();

    preg_match('/<aside class="app-sidebar.*?<\/aside>/s', (string) $html, $sidebar);
    preg_match_all('/<p>\s*([^<]+?)\s*(?:<i|<\/p>)/', $sidebar[0] ?? '', $labels);

    return array_map(fn (string $label): string => html_entity_decode(trim($label)), $labels[1]);
}

it('shows Front Desk and Accountant different sidebars', function (): void {
    expect(sidebarOf(DefaultRole::FrontDeskAgent))->toBe(['Dashboard', 'Front Office', 'Front Desk', 'Setup', 'Cottages', 'Rooms', 'Cottage types', 'Room types'])
        ->and(sidebarOf(DefaultRole::Accountant))->toBe(['Dashboard', 'Accounting', 'Journal Entries', 'Setup', 'Departments']);
});

it('shows the Tenant Owner everything, including setup', function (): void {
    expect(sidebarOf(DefaultRole::TenantOwner))->toBe([
        'Dashboard', 'Front Office', 'Front Desk', 'Accounting', 'Journal Entries',
        'Setup', 'Properties', 'Cottages', 'Rooms', 'Cottage types', 'Room types', 'Amenities', 'Departments', 'Users', 'Property access', 'Roles & permissions', 'Settings', 'Document numbering', 'Audit log',
    ]);
});

it('returns 403 for a direct URL to a forbidden page', function (DefaultRole $role, string $method, string $path): void {
    actingAs(tenantUserAs(tenant('sunrise'), $role));

    $response = $method === 'post' ? post(tenantUrl('sunrise', $path), []) : get(tenantUrl('sunrise', $path));

    $response->assertForbidden()->assertSee(__('You don\'t have access to this page'));
})->with([
    'front desk → journals' => [DefaultRole::FrontDeskAgent, 'get', '/_journals'],
    'accountant → front desk' => [DefaultRole::Accountant, 'get', '/_front-desk'],
    'front desk → users' => [DefaultRole::FrontDeskAgent, 'get', '/iam/users'],
    'front desk → users data' => [DefaultRole::FrontDeskAgent, 'get', '/iam/users/data'],
    'front desk → invite' => [DefaultRole::FrontDeskAgent, 'post', '/iam/users'],
    'front desk → roles' => [DefaultRole::FrontDeskAgent, 'get', '/iam/roles'],
    'auditor → invite' => [DefaultRole::Auditor, 'post', '/iam/users'],
    'auditor → new role' => [DefaultRole::Auditor, 'get', '/iam/roles/create'],
    'general manager → new role' => [DefaultRole::GeneralManager, 'get', '/iam/roles/create'],
]);

it('lets allowed roles in', function (DefaultRole $role, string $path): void {
    actingAs(tenantUserAs(tenant('sunrise'), $role));

    get(tenantUrl('sunrise', $path))->assertOk();
})->with([
    'front desk → front desk' => [DefaultRole::FrontDeskAgent, '/_front-desk'],
    'accountant → journals' => [DefaultRole::Accountant, '/_journals'],
    'auditor → users' => [DefaultRole::Auditor, '/iam/users'],
    'auditor → roles' => [DefaultRole::Auditor, '/iam/roles'],
    'general manager → users' => [DefaultRole::GeneralManager, '/iam/users'],
    'owner → new role' => [DefaultRole::TenantOwner, '/iam/roles/create'],
]);
