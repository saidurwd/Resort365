<?php

/*
| Paying online (Step 8.3 "Done when", with the demo's test gateway): a guest books a room on the website, pays the deposit through
| the gateway's page and comes back to a confirmed booking. Every page must leave the console clean.
*/

use Laravel\Dusk\Browser;

it('pays the deposit of a website booking online and confirms it', function (): void {
    $this->browse(function (Browser $browser): void {
        $checkIn = now()->toImmutable()->addDays(50);
        $browser->resize(430, 932);

        $this->visitClean($browser, $this->tenantPage('rodela', '/resort/rodela/book?check_in='.$checkIn->toDateString().'&check_out='.$checkIn->addDays(1)->toDateString().'&adults=2&children=0'));
        $browser->script("document.querySelector('[data-plan=\"RO\"] ~ [data-result^=\"room:\"] [data-add]').click()");
        $browser->waitFor('[data-flash="success"]', 20)->click('[data-checkout]')->waitFor('[data-checkout-form]', 20);

        $browser->type('first_name', 'Online')->type('last_name', 'Payer')->type('email', 'online.payer'.random_int(1000, 9999).'@example.com')
            ->type('phone', '01911-'.random_int(100000, 999999))->check('accept_policies')
            ->click('[data-book]')->waitFor('[data-pay-with="test"]', 20)
            ->assertPresent('[data-booking-status="tentative"]');
        $this->assertConsoleClean($browser);

        $browser->click('[data-pay-with="test"]')->waitFor('[data-test-pay]', 20);
        $this->assertConsoleClean($browser);

        $browser->click('[data-test-pay]')->waitFor('[data-payment-outcome]', 20)
            ->assertPresent('[data-payment-outcome="paid"]')
            ->assertPresent('[data-booking-status="confirmed"]');
        $this->assertConsoleClean($browser);
    });
});
