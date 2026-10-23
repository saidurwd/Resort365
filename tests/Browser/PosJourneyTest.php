<?php

/*
| POS journey (Step 7.6, ARCHITECTURE §13): a tablet registers as the Main Restaurant's cashier desk with its device token, the
| cashier signs in with their PIN, opens a takeaway, adds a dish, sends it to the kitchen, prints the bill and takes cash. The demo
| cashier session is open at the desk. Every screen must leave the console clean.
*/
use Laravel\Dusk\Browser;

it('takes a takeaway order from first item to cash on the POS', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->visitClean($browser, $this->tenantPage('rodela', '/pos/register'))
            ->type('token', 'DEMO-CASH-DESK-0001')
            ->press('Register')
            ->waitFor('[data-lock-screen]', 20);
        $this->assertConsoleClean($browser);

        $browser->click('[data-staff="cashier@rodelaresort.com"]');

        foreach (str_split('2222') as $digit) {
            $browser->click("[data-key=\"{$digit}\"]");
        }

        $browser->click('[data-key="enter"]')->waitForLocation('/pos/main', 20);
        $this->assertConsoleClean($browser)->assertPresent('[data-session-open]');

        $this->visitClean($browser, $this->tenantPage('rodela', '/pos/floor'))
            ->click('[data-takeaway]')
            ->waitFor('.pos-item', 20);
        $this->assertConsoleClean($browser);

        // A can of Coca-Cola (demo code SD02): one size and no choices, so it goes straight onto the order.
        $browser->click('[data-item="SD02"]')
            ->waitFor('[data-line]', 20)
            ->click('[data-send]')
            ->waitFor('[data-line-status="sent"]', 20);
        $this->assertConsoleClean($browser);

        $browser->click('[data-bill-button]')->waitFor('[data-bill-screen]', 20);
        $this->assertConsoleClean($browser);

        $browser->waitFor('[data-print-bill]:not([disabled])', 20)
            ->click('[data-print-bill]')
            ->waitFor('[data-pay]', 20)
            ->click('[data-pay]')
            ->waitFor('[data-method="cash"]', 20)
            ->click('[data-method="cash"]')
            ->click('[data-confirm-pay]')
            ->waitFor('[data-done]', 20);
        $this->assertConsoleClean($browser);
    });
});
