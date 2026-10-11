<?php

use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Exceptions\UnitNotConvertible;
use Modules\Inventory\Services\UnitConverter;
use Tests\TestCase;

uses(TestCase::class);

it('converts between units of one kind by their factors', function (): void {
    $converter = new UnitConverter;
    $kg = ['kind' => UnitKind::Weight, 'factor' => '1000'];
    $g = ['kind' => UnitKind::Weight, 'factor' => '1'];

    expect($converter->between('2.5', $kg, $g))->toBe('2500')
        ->and($converter->between('250', $g, $kg))->toBe('0.25')
        ->and($converter->between('3', $kg, $kg))->toBe('3')
        ->and($converter->between('1', ['kind' => UnitKind::Volume, 'factor' => '1000'], ['kind' => UnitKind::Volume, 'factor' => '1']))->toBe('1000')
        ->and($converter->between('2', ['kind' => UnitKind::Count, 'factor' => '12'], ['kind' => UnitKind::Count, 'factor' => '1']))->toBe('24');
});

it('keeps six decimals and rounds half up', function (): void {
    expect((new UnitConverter)->between('1', ['kind' => UnitKind::Weight, 'factor' => '1'], ['kind' => UnitKind::Weight, 'factor' => '3']))->toBe('0.333333')
        ->and((new UnitConverter)->between('1', ['kind' => UnitKind::Weight, 'factor' => '2'], ['kind' => UnitKind::Weight, 'factor' => '3']))->toBe('0.666667');
});

it('refuses a conversion between kinds', function (): void {
    (new UnitConverter)->between('1', ['kind' => UnitKind::Weight, 'factor' => '1'], ['kind' => UnitKind::Volume, 'factor' => '1']);
})->throws(UnitNotConvertible::class);

it('applies an item\'s own factor', function (): void {
    expect((new UnitConverter)->viaItemFactor('3', '24'))->toBe('72')->and((new UnitConverter)->viaItemFactor('0.5', '25'))->toBe('12.5');
});
