<?php

namespace App\Support\Enums;

/**
 * Helpers for backed enums that implement HasLabelAndColor.
 *
 * @phpstan-require-implements HasLabelAndColor
 */
trait EnumHelpers
{
    /**
     * Case values, e.g. for an `in:` validation rule.
     *
     * @return list<int|string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Value => label pairs, e.g. for a select input.
     *
     * @return array<int|string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
