<?php

use function Pest\Laravel\get;

it('renders the welcome page', function (): void {
    get('/')->assertOk();
});
