<?php

use App\Support\Ui\FormField;

it('converts HTML field names to validation keys', function (string $name, string $key): void {
    expect(FormField::key($name))->toBe($key);
})->with([
    ['email', 'email'],
    ['guests[0][name]', 'guests.0.name'],
    ['tags[]', 'tags'],
    ['rooms[12][]', 'rooms.12'],
]);

it('derives stable element ids', function (): void {
    expect(FormField::id('guests[0][name]'))->toBe('field-guests-0-name')
        ->and(FormField::id('email'))->toBe('field-email');
});
