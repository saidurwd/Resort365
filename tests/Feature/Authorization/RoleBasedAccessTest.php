<?php

/*
| Step 0.6 "Done when": a Front Desk user sees a different sidebar from the Accountant, and a
| direct URL to a forbidden page returns 403. Accounting and Front Office are real (Steps 4.1, 2.2); the
| /_front-desk and /_journals stand-in pages only check their permissions (the real screens need a property).
*/

use App\Models\Tenant;
use App\Support\Authorization\DefaultRole;
use App\Support\Menu\MenuRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::domain('{tenant}.'.config('tenancy.central_domain'))->middleware(['web', 'tenant', 'auth'])->group(function (): void {
        Route::get('/_front-desk', fn (): string => 'desk')->middleware('can:frontoffice.desk.view')->name('test.front-desk');
        Route::get('/_journals', fn (): string => 'journals')->middleware('can:accounting.journal.view')->name('test.journals');
    });
    Route::getRoutes()->refreshNameLookups();

    $menu = app(MenuRegistry::class);

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
    expect(sidebarOf(DefaultRole::FrontDeskAgent))->toBe(['Dashboard', 'Front Office', 'Front Desk', 'Housekeeping', 'Room status', 'Report a fault', 'Lost & found', 'Reservations', 'New booking', 'Reservations', 'Quotes', 'Tape chart', 'Availability', 'Billing', 'My cashier shift', 'Guests', 'Guests', 'Companies', 'Travel agents', 'Rates', 'Rate grid', 'Rate plans', 'Seasons', 'Policies', 'Promotions', 'Setup', 'Cottages', 'Rooms', 'Cottage types', 'Room types'])
        ->and(sidebarOf(DefaultRole::Accountant))->toBe(['Dashboard', 'Front Office', 'Night audit', 'Flash report', 'Housekeeping', 'Report a fault', 'Restaurant', 'POS sessions', 'Reports', 'Accounting', 'Journal entries', 'Chart of accounts', 'Account mapping', 'Fiscal periods', 'Billing', 'Cashier shifts', 'City ledger', 'Guests', 'Companies', 'Travel agents', 'Setup', 'Departments', 'Taxes', 'Charge codes', 'Extras']);
});

it('shows the Tenant Owner everything, including setup', function (): void {
    expect(sidebarOf(DefaultRole::TenantOwner))->toBe([
        'Dashboard', 'Front Office', 'Front Desk', 'Night audit', 'Flash report', 'Housekeeping', 'Room status', 'Tasks', 'My tasks', 'Out of order', 'Work orders', 'Report a fault', 'Preventive maintenance', 'Lost & found', 'Restaurant', 'Outlets', 'Table reservations', 'Menu', 'Menu categories', 'Modifiers', 'POS sessions', 'Kitchen display', 'Printers', 'Reports', 'Discount limits', 'Outlet access', 'Accounting', 'Journal entries', 'Chart of accounts', 'Account mapping', 'Fiscal periods', 'Reservations', 'New booking', 'Reservations', 'Quotes', 'Tape chart', 'Availability', 'Booking sources', 'Billing', 'My cashier shift', 'Cashier shifts', 'City ledger', 'Guests', 'Guests', 'Companies', 'Travel agents', 'Rates', 'Rate grid', 'Rate plans', 'Seasons', 'Policies', 'Promotions',
        'Setup', 'Properties', 'Cottages', 'Rooms', 'Cottage types', 'Room types', 'Amenities', 'Departments', 'Taxes', 'Users', 'Property access', 'Roles & permissions', 'Settings', 'Email templates', 'Charge codes', 'Extras', 'Document numbering', 'Audit log',
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
