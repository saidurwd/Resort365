<?php

use Tests\Fixtures\RoomStatus;

it('lists the case values', function (): void {
    expect(RoomStatus::values())->toBe(['clean', 'dirty']);
});

it('maps values to labels for select inputs', function (): void {
    expect(RoomStatus::options())->toBe(['clean' => 'Clean', 'dirty' => 'Dirty']);
});

it('exposes a label and a badge colour per case', function (): void {
    expect(RoomStatus::Dirty->label())->toBe('Dirty')
        ->and(RoomStatus::Dirty->color())->toBe('warning');
});
