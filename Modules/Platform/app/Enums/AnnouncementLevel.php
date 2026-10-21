<?php

namespace Modules\Platform\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum AnnouncementLevel: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Info = 'info';
    case Warning = 'warning';
    case Danger = 'danger';

    public function label(): string
    {
        return match ($this) {
            self::Info => __('Information'),
            self::Warning => __('Warning'),
            self::Danger => __('Urgent'),
        };
    }

    public function color(): string
    {
        return $this->value;
    }
}
