<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Whether a payment gateway takes test payments (its sandbox) or real ones (Step 8.3).
 */
enum GatewayMode: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Sandbox = 'sandbox';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Sandbox => __('Sandbox (test payments)'),
            self::Live => __('Live (real payments)'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Sandbox => 'warning',
            self::Live => 'success',
        };
    }
}
