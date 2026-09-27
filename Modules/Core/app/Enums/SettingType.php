<?php

namespace Modules\Core\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a setting is validated, cast and edited.
 */
enum SettingType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Text = 'text';
    case Integer = 'integer';
    case Decimal = 'decimal';
    case Boolean = 'boolean';
    case Time = 'time';
    case Select = 'select';
    case Currency = 'currency';
    case Country = 'country';
    case Timezone = 'timezone';

    public function label(): string
    {
        return match ($this) {
            self::Text => __('Text'),
            self::Integer => __('Whole number'),
            self::Decimal => __('Decimal number'),
            self::Boolean => __('Yes / no'),
            self::Time => __('Time'),
            self::Select => __('Choice'),
            self::Currency => __('Currency'),
            self::Country => __('Country'),
            self::Timezone => __('Timezone'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }

    /**
     * Validation rules for a value of this type.
     *
     * @param  array<string, string>  $options
     * @return list<mixed>
     */
    public function rules(array $options = []): array
    {
        return match ($this) {
            self::Text => ['string', 'max:255'],
            self::Integer => ['integer'],
            self::Decimal => ['numeric', 'decimal:0,4'],
            self::Boolean => ['boolean'],
            self::Time => ['date_format:H:i'],
            self::Select => ['string', 'in:'.implode(',', array_keys($options))],
            self::Currency => ['string', 'size:3', 'exists:currencies,code'],
            self::Country => ['string', 'size:2', 'exists:countries,code'],
            self::Timezone => ['string', 'exists:timezones,name'],
        };
    }

    /**
     * Cast a stored (JSON-decoded) value to this type. Decimals stay strings (no floats).
     */
    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this) {
            self::Integer => (int) $value,
            self::Decimal => (string) $value,
            self::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => (string) $value,
        };
    }
}
