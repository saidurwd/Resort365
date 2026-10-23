<?php

/*
| Front desk journey (Step 7.6, ARCHITECTURE §13): the front desk agent checks in the demo arrival in room 201 (recording the
| guest's ID first) and checks out the guest leaving room 402 (posting the room nights and settling the folio). Uses demo data no
| other journey touches; every page must leave the console clean.
*/
use Laravel\Dusk\Browser;

/** The link of a row's button on the front desk for the room, e.g. the check-in of the booking in room 201. */
function fdRowLink(Browser $browser, string $list, string $room, string $action): string
{
    $href = $browser->script(["const row = Array.from(document.querySelectorAll('[data-list={$list}] tr[data-booking]')).find(tr => tr.textContent.includes('{$room}')); return row ? row.querySelector('[data-{$action}]').href : null;"])[0];
    expect($href)->toBeString("The front desk lists no {$action} for room {$room}");

    return (string) $href;
}

it('checks a guest in and another out at the front desk', function (): void {
    $this->browse(function (Browser $browser): void {
        $this->signIn($browser, 'frontdesk@rodelaresort.com');
        $this->visitClean($browser, $this->tenantPage('rodela', '/frontoffice'));

        // Check in room 201.
        $this->visitClean($browser, fdRowLink($browser, 'arrivals', '201', 'check-in'));

        if ($browser->element('[data-check-in-form] button[disabled]') !== null) {
            $browser->script("document.querySelector('[name=id_type]').tomselect.setValue('passport')");
            $browser->type('id_number', 'P'.random_int(1000000, 9999999))
                ->type('nationality_code', 'BD')
                ->click('[data-identity-form] button')
                ->waitFor('[data-check-in-form] button:not([disabled])', 20);
            $this->assertConsoleClean($browser);
        }

        $browser->click('[data-check-in-form] button')->waitForText('checked in', 20);
        $this->assertConsoleClean($browser);

        // Check out room 402.
        $this->visitClean($browser, $this->tenantPage('rodela', '/frontoffice'));
        $this->visitClean($browser, fdRowLink($browser, 'departures', '402', 'check-out'));

        if ($browser->element('[data-post-charges] button') !== null) {
            $browser->click('[data-post-charges] button')->waitFor('[data-charges-posted]', 20);
            $this->assertConsoleClean($browser);
        }

        foreach ($browser->elements('[data-settle] button') as $ignored) {
            $browser->click('[data-settle] button')->waitUntilMissing('.spinner-border', 20)->pause(500);
            $this->assertConsoleClean($browser);
        }

        $browser->waitFor('[data-check-out-form] button:not([disabled])', 20)
            ->click('[data-check-out-form] button')
            ->waitForText('checked out', 20);
        $this->assertConsoleClean($browser);
    });
});
