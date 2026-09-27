<?php

use Tests\Fixtures\GuestData;

it('builds a DTO from an array', function (): void {
    $guest = GuestData::from(['name' => 'Rahim Uddin', 'email' => 'rahim@example.com']);

    expect($guest)->toBeInstanceOf(GuestData::class)
        ->and($guest->name)->toBe('Rahim Uddin')
        ->and($guest->email)->toBe('rahim@example.com');
});

it('uses constructor defaults for missing optional keys', function (): void {
    expect(GuestData::from(['name' => 'Rahim Uddin'])->email)->toBeNull();
});

it('rejects unknown keys', function (): void {
    GuestData::from(['name' => 'Rahim Uddin', 'phone' => '01700000000']);
})->throws(Error::class);

it('converts a DTO to an array', function (): void {
    expect(new GuestData('Rahim Uddin')->toArray())
        ->toBe(['name' => 'Rahim Uddin', 'email' => null]);
});
