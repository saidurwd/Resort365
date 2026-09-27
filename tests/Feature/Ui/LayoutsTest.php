<?php

it('renders the admin layout with title, breadcrumbs and actions', function (): void {
    blade(<<<'BLADE'
        <x-layouts::app title="Rooms" :breadcrumbs="['Setup' => '/setup', 'Rooms' => null]">
            <x-slot:actions><a href="/rooms/create">Add room</a></x-slot:actions>
            <p>Room list</p>
        </x-layouts::app>
        BLADE)
        ->assertSee('<title>Rooms · '.config('app.name').'</title>', false)
        ->assertSee('app-sidebar', false)
        ->assertSee('app-header', false)
        ->assertSee('app-footer', false)
        ->assertSee('<li class="breadcrumb-item"><a href="/setup">Setup</a></li>', false)
        ->assertSee('<li class="breadcrumb-item active" aria-current="page">Rooms</li>', false)
        ->assertSee('Add room')
        ->assertSee('Room list');
});

it('applies the saved colour mode before first paint', function (): void {
    blade('<x-layouts::app title="Rooms">x</x-layouts::app>')
        ->assertSee("localStorage.getItem('lte-theme')", false)
        ->assertSeeInOrder(['data-bs-theme-value="light"', 'data-bs-theme-value="dark"', 'data-bs-theme-value="auto"'], false);
});

it('renders the guest layout without the sidebar', function (): void {
    blade('<x-layouts::guest title="Sign in"><form>login</form></x-layouts::guest>')
        ->assertSee('login-box', false)
        ->assertSee('<form>login</form>', false)
        ->assertDontSee('app-sidebar', false);
});

it('renders the print layout in light mode with a print button', function (): void {
    blade('<x-layouts::print title="Invoice">INV-1</x-layouts::print>')
        ->assertSee('data-bs-theme="light"', false)
        ->assertSee('window.print()', false)
        ->assertSee('INV-1')
        ->assertDontSee('app-sidebar', false);
});
