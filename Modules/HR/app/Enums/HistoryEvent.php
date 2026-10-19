<?php

namespace Modules\HR\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum HistoryEvent: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Joined = 'joined';
    case Confirmation = 'confirmation';
    case Transfer = 'transfer';
    case Promotion = 'promotion';
    case Increment = 'increment';
    case Disciplinary = 'disciplinary';
    case Exit = 'exit';

    public function label(): string
    {
        return match ($this) {
            self::Joined => __('Joined'),
            self::Confirmation => __('Confirmation'),
            self::Transfer => __('Transfer'),
            self::Promotion => __('Promotion'),
            self::Increment => __('Increment'),
            self::Disciplinary => __('Disciplinary'),
            self::Exit => __('Exit'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Joined => 'success',
            self::Confirmation => 'success',
            self::Transfer => 'info',
            self::Promotion => 'primary',
            self::Increment => 'primary',
            self::Disciplinary => 'danger',
            self::Exit => 'secondary',
        };
    }
}
