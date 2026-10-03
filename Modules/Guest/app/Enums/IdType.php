<?php

namespace Modules\Guest\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The identity document a guest shows at check-in.
 */
enum IdType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case NationalId = 'national_id';
    case Passport = 'passport';
    case DrivingLicence = 'driving_licence';
    case BirthCertificate = 'birth_certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NationalId => __('National ID'),
            self::Passport => __('Passport'),
            self::DrivingLicence => __('Driving licence'),
            self::BirthCertificate => __('Birth certificate'),
            self::Other => __('Other'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Passport => 'primary',
            self::NationalId => 'info',
            default => 'secondary',
        };
    }
}
