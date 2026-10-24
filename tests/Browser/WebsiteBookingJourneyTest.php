<?php

/*
| Website booking journey (Step 8.2b "Done when"): on a phone, a guest browses Rodela's cottages, searches dates, adds two rooms to
| the booking, checks out, and lands on the booking's page as tentative; the reservations agent then sees it on the tape chart with
| both rooms held. Every page must leave the console clean.
*/

use Laravel\Dusk\Browser;

it('books two rooms on the website from a phone and shows them on the tape chart', function (): void {
    $this->browse(function (Browser $browser): void {
        $checkIn = now()->toImmutable()->addDays(45);
        $checkOut = $checkIn->addDays(2);
        $browser->resize(430, 932);

        $this->visitClean($browser, $this->tenantPage('rodela', '/'));
        $browser->assertPathIs('/resort/rodela');
        $this->visitClean($browser, $this->tenantPage('rodela', '/resort/rodela/rooms'))->assertPresent('[data-unit]');
        $this->visitClean($browser, $this->tenantPage('rodela', '/resort/rodela/book?check_in='.$checkIn->toDateString().'&check_out='.$checkOut->toDateString().'&adults=2&children=0'))
            ->assertPresent('[data-plan="RO"]');

        // Two rooms of the first room type of the Room Only plan.
        foreach ([1, 2] as $ignored) {
            $browser->script("document.querySelector('[data-plan=\"RO\"] ~ [data-result^=\"room:\"] [data-add]').scrollIntoView()");
            $browser->script("document.querySelector('[data-plan=\"RO\"] ~ [data-result^=\"room:\"] [data-add]').click()");
            $browser->waitFor('[data-flash="success"]', 20);
            $this->assertConsoleClean($browser);
        }

        $browser->assertPresent('[data-cart-item="1"]')->click('[data-checkout]')->waitFor('[data-checkout-form]', 20);
        $this->assertConsoleClean($browser);

        $browser->type('first_name', 'Dusk')->type('last_name', 'Journey')->type('email', 'dusk.journey'.random_int(1000, 9999).'@example.com')
            ->type('phone', '01811-'.random_int(100000, 999999))
            ->check('accept_policies')
            ->click('[data-book]')
            ->waitFor('[data-booking-status]', 20)
            ->assertPresent('[data-booking-status="tentative"]')
            ->assertPresent('[data-pay-now]');
        $this->assertConsoleClean($browser);
        $code = trim((string) $browser->script(["return document.querySelector('h1').textContent.match(/RSV-[\\d-]+/)[0]"])[0]);

        $browser->resize(1440, 1000);
        $this->signIn($browser, 'reservations@rodelaresort.com');
        $this->visitClean($browser, $this->tenantPage('rodela', '/reservation/tape-chart?days=14&from='.$checkIn->subDays(2)->toDateString()));
        expect((int) $browser->script(["return document.querySelectorAll('[data-bar][data-code=\"{$code}\"]').length"])[0])->toBe(2);
    });
});
