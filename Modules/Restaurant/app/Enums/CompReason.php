<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Why a bill is complimentary (ARCHITECTURE §5.10.7).
 */
enum CompReason: string implements HasLabelAndColor
{
    use EnumHelpers;

    case ManagementGuest = 'management_guest';
    case ServiceRecovery = 'service_recovery';
    case StaffMeal = 'staff_meal';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ManagementGuest => __('Management guest'),
            self::ServiceRecovery => __('Service recovery'),
            self::StaffMeal => __('Staff meal'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return 'warning';
    }
}
