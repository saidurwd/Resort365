<?php

namespace Modules\Reports\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum ExportStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Queued = 'queued';
    case Ready = 'ready';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Being made'),
            self::Ready => __('Ready'),
            self::Failed => __('Failed'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }
}
