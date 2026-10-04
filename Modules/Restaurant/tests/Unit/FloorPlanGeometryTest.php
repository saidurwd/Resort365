<?php

/*
| FloorPlanGeometry: table positions on a dining area's 1000 × 600 canvas.
*/

use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Services\FloorPlanGeometry;

it('sizes tables by shape and seats', function (): void {
    expect(TableShape::Square->size(4))->toBe([70, 70])
        ->and(TableShape::Round->size(2))->toBe([60, 60])
        ->and(TableShape::Round->size(6))->toBe([90, 90])
        ->and(TableShape::Rectangle->size(8))->toBe([200, 70]);
});

it('snaps positions to the grid and keeps the table inside the canvas', function (): void {
    $geometry = new FloorPlanGeometry;

    expect($geometry->place(TableShape::Square, 4, 123.4, 56.7))->toBe([120, 60])
        ->and($geometry->place(TableShape::Square, 4, -40, -5))->toBe([0, 0])
        ->and($geometry->place(TableShape::Square, 4, 990, 590))->toBe([930, 530])
        ->and($geometry->place(TableShape::Rectangle, 8, 900, 10))->toBe([800, 10]);
});

it('finds a free spot that does not overlap the other tables', function (): void {
    $geometry = new FloorPlanGeometry;
    $taken = [['x' => 20, 'y' => 20, 'w' => 70, 'h' => 70], ['x' => 100, 'y' => 20, 'w' => 70, 'h' => 70]];

    expect($geometry->freeSpot(TableShape::Square, 4, []))->toBe([20, 20])
        ->and($geometry->freeSpot(TableShape::Square, 4, $taken))->toBe([180, 20]);
});
