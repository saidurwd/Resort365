<?php

/*
| Browser smoke tour (Step 7.6): every page in the Tenant Owner's sidebar (which lists every screen) opens without a server error or
| a console error, and every default role can sign in and open its dashboard and the first page of its own sidebar. Catches pages
| the security policy or a script breaks, beyond the four journeys.
*/
use App\Support\Authorization\DefaultRole;
use Laravel\Dusk\Browser;

/**
 * The sidebar's page links on the current page, without duplicates.
 *
 * @return list<string>
 */
function stSidebarLinks(Browser $browser): array
{
    return array_values(array_unique((array) $browser->script([
        "return Array.from(document.querySelectorAll('.app-sidebar a.nav-link[href]')).map(a => a.href).filter(h => h.startsWith(location.origin) && ! h.endsWith('#'))",
    ])[0]));
}

it('opens every page in the owner\'s sidebar with a clean console', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->signIn($browser, 'owner@rodelaresort.com');
        $links = stSidebarLinks($browser);
        expect(count($links))->toBeGreaterThan(80);

        foreach ($links as $link) {
            $this->visitClean($browser, $link);
            $title = (string) $browser->driver->getTitle();
            expect($title)->not->toContain('Server Error', $link)->not->toContain('Not Found', $link)->not->toContain('Forbidden', $link);
        }
    });
});

it('lets every default role sign in and open its own pages', function (): void {
    $this->browse(function (Browser $browser): void {
        foreach (DefaultRole::cases() as $role) {
            $this->signOut($browser);
            $this->signIn($browser, $role->demoMailbox().'@rodelaresort.com');
            $first = stSidebarLinks($browser)[1] ?? null;

            if ($first !== null) {
                $this->visitClean($browser, $first);
                expect((string) $browser->driver->getTitle())->not->toContain('Forbidden', $role->value);
            }
        }
    });
});
