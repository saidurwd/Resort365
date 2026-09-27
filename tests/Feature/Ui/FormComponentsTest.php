<?php

use Carbon\CarbonImmutable;

it('renders an input with label, required marker and help text', function (): void {
    blade('<x-form.input name="guest_name" label="Guest name" required help="As on the passport." />')
        ->assertSee('<label for="field-guest_name" class="form-label">', false)
        ->assertSee('required-marker', false)
        ->assertSee('required', false)
        ->assertSee('aria-describedby="field-guest_name-help"', false)
        ->assertSee('As on the passport.');
});

it('marks an input invalid and shows its error', function (): void {
    withViewErrors(['guests.0.name' => 'The name is required.']);

    blade('<x-form.input name="guests[0][name]" label="Name" />')
        ->assertSee('id="field-guests-0-name"', false)
        ->assertSee('is-invalid', false)
        ->assertSee('aria-invalid="true"', false)
        ->assertSee('The name is required.');
});

it('prefers old input over the given value', function (): void {
    session()->flashInput(['email' => 'old@example.com']);
    request()->setLaravelSession(session()->driver());

    blade('<x-form.input name="email" type="email" value="new@example.com" />')
        ->assertSee('value="old@example.com"', false);
});

it('never echoes a password value', function (): void {
    blade('<x-form.input name="password" type="password" value="secret" />')
        ->assertDontSee('secret');
});

it('renders a Tom Select select with the chosen option', function (): void {
    blade('<x-form.select name="cottage" :options="[1 => \'Family Villa\', 2 => \'Honeymoon\']" value="2" />')
        ->assertSee('data-tom-select', false)
        ->assertSee('<option value="2" selected>Honeymoon</option>', false)
        ->assertSee('<option value="1" >Family Villa</option>', false)
        ->assertSee('<option value="">', false);
});

it('renders a multiple select with array name and several selections', function (): void {
    blade('<x-form.select name="extras" multiple :options="[\'wifi\' => \'Wi-Fi\', \'spa\' => \'Spa\', \'bbq\' => \'BBQ\']" :value="[\'wifi\', \'bbq\']" />')
        ->assertSee('name="extras[]"', false)
        ->assertSee('<option value="wifi" selected>', false)
        ->assertSee('<option value="bbq" selected>', false)
        ->assertDontSee('<option value="spa" selected>', false);
});

it('renders a plain select when search is off', function (): void {
    blade('<x-form.select name="gender" :options="[\'f\' => \'Female\']" :search="false" />')
        ->assertDontSee('data-tom-select', false);
});

it('renders a flatpickr date input with its options', function (): void {
    blade('<x-form.date name="arrival_date" value="2026-10-12" min="2026-10-01" />')
        ->assertSee('value="2026-10-12"', false)
        ->assertSee('data-flatpickr="{&quot;minDate&quot;:&quot;2026-10-01&quot;}"', false);
});

it('formats a Carbon value for the date input', function (): void {
    blade('<x-form.date name="arrival_date" :value="$date" />', ['date' => CarbonImmutable::parse('2026-10-12 14:00')])
        ->assertSee('value="2026-10-12"', false);
});

it('renders a money input as a decimal string with its currency', function (): void {
    blade('<x-form.money name="deposit" currency="BDT" value="7500.00" />')
        ->assertSee('<span class="input-group-text">BDT</span>', false)
        ->assertSee('inputmode="decimal"', false)
        ->assertSee('value="7500.00"', false)
        ->assertSee('pattern="\d+(\.\d{1,2})?"', false)
        ->assertSee('input-money', false);
});

it('allows negative money amounts only when asked', function (): void {
    blade('<x-form.money name="adjustment" allow-negative />')
        ->assertSee('pattern="-?\d+(\.\d{1,2})?"', false);
});
