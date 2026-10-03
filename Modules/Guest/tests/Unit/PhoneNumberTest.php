<?php

use Modules\Guest\Services\PhoneNumber;

it('stores phone numbers in international format', function (?string $input, ?string $expected): void {
    expect(new PhoneNumber()->normalize($input, '880'))->toBe($expected);
})->with([
    'local mobile' => ['01711-000000', '+8801711000000'],
    'local with spaces' => ['017 1100 0000', '+8801711000000'],
    'international with plus' => ['+880 1711 000000', '+8801711000000'],
    'international with 00' => ['0044 20 7946 0000', '+442079460000'],
    'country code without plus' => ['8801711000000', '+8801711000000'],
    'without the leading zero' => ['1711000000', '+8801711000000'],
    'UK number' => ['+44 (20) 7946-0000', '+442079460000'],
    'empty' => ['', null],
    'null' => [null, null],
    'no digits' => ['n/a', null],
]);

it('uses the given calling code for local numbers', function (): void {
    expect(new PhoneNumber()->normalize('020 7946 0000', '44'))->toBe('+442079460000');
});
