<?php

namespace Modules\Core\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum TenantExportStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Queued = 'queued';
    case Ready = 'ready';
    case Failed = 'failed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Queued => __('Being prepared'),
            self::Ready => __('Ready'),
            self::Failed => __('Failed'),
            self::Expired => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'info',
            self::Ready => 'success',
            self::Failed => 'danger',
            self::Expired => 'secondary',
        };
    }
}
