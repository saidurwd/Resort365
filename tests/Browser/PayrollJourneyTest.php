<?php

/*
| Payroll journey (Step 7.6, ARCHITECTURE §13): the payroll officer sends this month's demo run for approval (confirming the
| dialog), the accountant approves it and, when the run is large enough, the General Manager too, and the accountant marks it paid.
| Every page must leave the console clean.
*/
use Laravel\Dusk\Browser;

/** Approves the run's pending step as the person, from the Approvals page, when that person has a step to decide. */
function pjApprove(Browser $browser, object $test, string $email, string $approvalUrl): void
{
    $test->signOut($browser);
    $test->signIn($browser, $email);
    $test->visitClean($browser, $approvalUrl);

    if ($browser->element('button[formaction$="/approve"]') !== null) {
        $browser->click('button[formaction$="/approve"]')->waitForText('Approved', 20);
        $test->assertConsoleClean($browser);
    }
}

it('sends a payroll run for approval, approves it and pays it', function (): void {
    $this->browse(function (Browser $browser): void {
        $month = now()->format('Y-m');
        $this->signIn($browser, 'payroll@rodelaresort.com');
        $this->visitClean($browser, $this->tenantPage('rodela', '/payroll/runs'));
        $runUrl = (string) $browser->script(["return document.querySelector('[data-run=\"{$month}\"] a').href"])[0];
        $this->visitClean($browser, $runUrl);

        if ($browser->element('[data-submit]') !== null) {
            $browser->click('[data-submit]')
                ->waitFor('.swal2-confirm', 10)
                ->click('.swal2-confirm')
                ->waitFor('[data-approval]', 20);
            $this->assertConsoleClean($browser);
        }

        $approvalUrl = (string) $browser->script(["return document.querySelector('[data-approval] a').href"])[0];
        // The accountant's step always; the General Manager's only for a large run (from 2,000,000).
        pjApprove($browser, $this, 'accountant@rodelaresort.com', $approvalUrl);
        pjApprove($browser, $this, 'gm@rodelaresort.com', $approvalUrl);

        // Paying is the accounts' job (payroll.run.pay: accountant and General Manager), not the payroll officer's.
        $this->signOut($browser);
        $this->signIn($browser, 'accountant@rodelaresort.com');
        $this->visitClean($browser, $runUrl)
            ->type('payment_reference', 'DUSK-'.random_int(1000, 9999))
            ->press('Mark paid')
            ->waitFor('[data-paid]', 20);
        $this->assertConsoleClean($browser);
    });
});
