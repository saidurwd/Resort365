<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Enums\TableShape;

/**
 * The floor-plan canvas (ARCHITECTURE §5.10.3), without the database: a dining area is a 1000 × 600
 * canvas; a table's position is its top-left corner, snapped to a 10-unit grid and kept inside the
 * canvas for its size (TableShape::size()).
 */
class FloorPlanGeometry
{
    public const int WIDTH = 1000;

    public const int HEIGHT = 600;

    public const int GRID = 10;

    /**
     * @return array{int, int} x, y on the canvas
     */
    public function place(TableShape $shape, int $seats, int|float $x, int|float $y): array
    {
        [$width, $height] = $shape->size($seats);
        $snap = fn (int|float $value, int $max): int => max(0, min($max, (int) (round($value / self::GRID) * self::GRID)));

        return [$snap($x, self::WIDTH - $width), $snap($y, self::HEIGHT - $height)];
    }

    /**
     * Where to put a new table: the first free grid slot, left to right, top to bottom.
     *
     * @param  list<array{x: int, y: int, w: int, h: int}>  $taken
     * @return array{int, int}
     */
    public function freeSpot(TableShape $shape, int $seats, array $taken): array
    {
        [$width, $height] = $shape->size($seats);

        for ($y = 20; $y + $height <= self::HEIGHT; $y += 20) {
            for ($x = 20; $x + $width <= self::WIDTH; $x += 20) {
                $clear = array_all($taken, fn (array $box): bool => ! ($x < $box['x'] + $box['w'] + 10 && $box['x'] < $x + $width + 10 && $y < $box['y'] + $box['h'] + 10 && $box['y'] < $y + $height + 10));
                if ($clear) {
                    return [$x, $y];
                }
            }
        }

        return [0, 0];
    }
}
