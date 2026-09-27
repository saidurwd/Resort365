<?php

use App\Models\Tenant;
use App\Models\TenantModule;
use App\Support\Menu\MenuItem;
use App\Support\Menu\MenuRegistry;
use App\Support\Tenancy\ModuleAccess;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * A user who holds exactly the given abilities.
 *
 * @param  list<string>  $abilities
 */
function userWith(array $abilities): AuthUser
{
    return new class($abilities) extends AuthUser
    {
        /** @param list<string> $abilities */
        public function __construct(private readonly array $abilities = [])
        {
            parent::__construct();
        }

        /**
         * @param  iterable<mixed>|string  $abilities
         * @param  array<mixed>|mixed  $arguments
         */
        public function can($abilities, $arguments = []): bool
        {
            return in_array($abilities, $this->abilities, true);
        }
    };
}

function sampleMenu(): MenuRegistry
{
    foreach (['test.desk', 'test.journals', 'test.users', 'test.hidden'] as $name) {
        Route::get('/_menu/'.$name, fn (): string => 'ok')->name($name);
    }

    // Routes named after being added are only found once the name index is rebuilt.
    Route::getRoutes()->refreshNameLookups();

    $menu = new MenuRegistry;
    $menu->add(new MenuItem('dash', 'Dashboard', 'bi-speedometer2', route: 'test.desk', order: 0));
    $menu->group('fo', 'Front Office', 'bi-door-open', order: 100);
    $menu->add(new MenuItem('fo.desk', 'Front Desk', route: 'test.desk', parent: 'fo', permission: 'frontoffice.desk.view', module: 'frontoffice'));
    $menu->group('acc', 'Accounting', 'bi-journal', order: 200);
    $menu->add(new MenuItem('acc.journals', 'Journals', route: 'test.journals', parent: 'acc', permission: 'accounting.journal.view', module: 'accounting'));
    $menu->group('setup', 'Setup', 'bi-gear', order: 900);
    $menu->add(new MenuItem('setup.users', 'Users', route: 'test.users', parent: 'setup', order: 20, permission: 'iam.user.view'));
    $menu->add(new MenuItem('setup.hidden', 'Hidden', route: 'test.hidden', parent: 'setup', order: 10, visible: fn (): bool => false));
    $menu->add(new MenuItem('setup.missing', 'Missing route', route: 'no.such.route', parent: 'setup'));

    return $menu;
}

/**
 * @param  list<array<string, mixed>>  $items
 * @return list<string>
 */
function labels(array $items): array
{
    $labels = [];

    foreach ($items as $item) {
        $labels[] = (string) $item['label'];

        foreach ($item['children'] ?? [] as $child) {
            $labels[] = '  '.$child['label'];
        }
    }

    return $labels;
}

it('shows only the items a user may see, in order, without empty groups', function (): void {
    $menu = sampleMenu();

    expect(labels($menu->forUser(userWith(['frontoffice.desk.view']))))->toBe(['Dashboard', 'Front Office', '  Front Desk'])
        ->and(labels($menu->forUser(userWith(['accounting.journal.view', 'iam.user.view']))))->toBe(['Dashboard', 'Accounting', '  Journals', 'Setup', '  Users'])
        ->and(labels($menu->forUser(null)))->toBe(['Dashboard']);
});

it('hides items of modules disabled for the tenant', function (): void {
    $tenant = Tenant::factory()->create();
    app(TenantContext::class)->set($tenant);
    TenantModule::factory()->create(['module' => 'accounting', 'enabled' => false]);
    app(ModuleAccess::class)->flush();

    expect(labels(sampleMenu()->forUser(userWith(['accounting.journal.view', 'frontoffice.desk.view']))))
        ->toBe(['Dashboard', 'Front Office', '  Front Desk']);
});

it('marks the current page and its group active', function (): void {
    $menu = sampleMenu();
    get('/_menu/test.journals');

    $journals = collect($menu->forUser(userWith(['accounting.journal.view'])))->firstWhere('label', 'Accounting');

    expect($journals['active'] ?? null)->toBeTrue()
        ->and($journals['children'][0]['active'] ?? null)->toBeTrue();
});

it('rejects duplicate keys but shares groups', function (): void {
    $menu = new MenuRegistry;
    $menu->group('setup', 'Setup', 'bi-gear');
    $menu->group('setup', 'Setup again', 'bi-x');

    expect($menu->all()['setup']->label)->toBe('Setup')
        ->and(fn () => $menu->add(new MenuItem('setup', 'Dup')))->toThrow(InvalidArgumentException::class);
});
