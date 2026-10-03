<?php

namespace Modules\Property\Services;

use InvalidArgumentException;

/**
 * Room numbers for the quick "add cottage with N rooms" form: the trailing digits of the first
 * number count up, keeping any prefix and zero padding (101 → 101, 102…; A08 → A08, A09, A10).
 */
class RoomNumberSequence
{
    /**
     * Matches a number that can start a sequence (it ends in digits).
     */
    public const string PATTERN = '/^(.*?)(\d+)$/';

    /**
     * @return list<string>
     */
    public function generate(string $first, int $count): array
    {
        if (preg_match(self::PATTERN, $first, $matches) !== 1) {
            throw new InvalidArgumentException("Room number \"{$first}\" does not end in digits.");
        }

        [, $prefix, $digits] = $matches;
        $numbers = [];

        for ($i = 0; $i < $count; $i++) {
            $numbers[] = $prefix.str_pad((string) ((int) $digits + $i), strlen($digits), '0', STR_PAD_LEFT);
        }

        return $numbers;
    }
}
