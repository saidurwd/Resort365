<?php

/*
| Booking journey (Step 7.6, ARCHITECTURE §13): a reservations agent checks availability and the tape chart, then books a room for a
| new guest through the wizard and lands on the booking page. Every page must leave the console clean.
*/
use Carbon\CarbonImmutable;
use Laravel\Dusk\Browser;

it('books a room for a new guest through the wizard', function (): void {
    $this->browse(function (Browser $browser): void {
        $checkIn = CarbonImmutable::today()->addDays(random_int(60, 300));
        $checkOut = $checkIn->addDays(2);
        $guest = 'Dusk'.random_int(10000, 99999);

        $this->signIn($browser, 'reservations@rodelaresort.com');
        $this->visitClean($browser, $this->tenantPage('rodela', '/reservation/availability'))->assertSee('Availability');
        $this->visitClean($browser, $this->tenantPage('rodela', '/reservation/tape-chart'))->assertPresent('[x-data^="tapeChart"]');

        $this->visitClean($browser, $this->tenantPage('rodela', '/reservation/bookings/new'));
        $browser->script([
            "document.querySelector('[name=check_in]')._flatpickr.setDate('{$checkIn->toDateString()}', true)",
            "document.querySelector('[name=check_out]')._flatpickr.setDate('{$checkOut->toDateString()}', true)",
        ]);
        $browser->press('Find cottages and rooms')->waitForLocation('/reservation/bookings/new/choose', 20);
        $this->assertConsoleClean($browser);

        $browser->script("document.querySelector('input[name=\"rooms[]\"]:not(:disabled)').click()");
        $browser->press('Continue')->waitForLocation('/reservation/bookings/new/guest', 20);
        $this->assertConsoleClean($browser);

        $browser->radio('guest_mode', 'new')
            ->waitFor('[name=first_name]')
            ->type('first_name', $guest)
            ->type('last_name', 'Browser')
            ->type('phone', '017'.random_int(10000000, 99999999))
            ->press('Continue')
            ->waitForLocation('/reservation/bookings/new/pricing', 20);
        $this->assertConsoleClean($browser);

        $browser->press('Continue')->waitForLocation('/reservation/bookings/new/confirm', 20);
        $this->assertConsoleClean($browser)->assertSee($guest);

        $browser->press('Create booking')->waitForText($guest, 20);
        $this->assertConsoleClean($browser)
            ->assertPathBeginsWith('/reservation/bookings/')
            ->assertSee($checkIn->format('d M Y'));
    });
});
