<?php

namespace Modules\Inventory\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

enum StoreKind: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Main = 'main';
    case Kitchen = 'kitchen';
    case Bar = 'bar';
    case Housekeeping = 'housekeeping';
    case Maintenance = 'maintenance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Main => __('Main store'),
            self::Kitchen => __('Kitchen store'),
            self::Bar => __('Bar'),
            self::Housekeeping => __('Housekeeping store'),
            self::Maintenance => __('Maintenance store'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Main => 'primary',
            self::Kitchen => 'warning',
            self::Bar => 'info',
            self::Housekeeping => 'success',
            self::Maintenance => 'secondary',
            self::Other => 'secondary',
        };
    }
}
