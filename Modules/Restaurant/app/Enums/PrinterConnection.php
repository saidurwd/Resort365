<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How the POS reaches a printer: the browser's print dialog (v1), the network (ESC/POS) or a local print agent (later).
 */
enum PrinterConnection: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Browser = 'browser';
    case Network = 'network';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Browser => __('Browser printing'),
            self::Network => __('Network (ESC/POS)'),
            self::Agent => __('Print agent'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Browser => 'primary',
            self::Network => 'info',
            self::Agent => 'secondary',
        };
    }
}
