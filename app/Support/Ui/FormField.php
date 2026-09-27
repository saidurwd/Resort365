<?php

namespace App\Support\Ui;

/**
 * Naming helpers shared by the form components (resources/views/components/form).
 */
class FormField
{
    /**
     * Validation / old-input key for an HTML field name: `guests[0][name]` → `guests.0.name`, `tags[]` → `tags`.
     */
    public static function key(string $name): string
    {
        return trim(str_replace(['[]', '[', ']'], ['', '.', ''], $name), '.');
    }

    /**
     * Stable element id for an HTML field name: `guests[0][name]` → `field-guests-0-name`.
     */
    public static function id(string $name): string
    {
        return 'field-'.str_replace('.', '-', self::key($name));
    }
}
