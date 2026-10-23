<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Where a booking came from.
 */
enum ReservationSource: string implements HasLabelAndColor
{
    use EnumHelpers;

    case FrontDesk = 'front_desk';
    case Phone = 'phone';
    case Email = 'email';
    case WalkIn = 'walk_in';
    case TravelAgent = 'travel_agent';
    case Corporate = 'corporate';
    case Online = 'online';
    case Api = 'api';

    /**
     * The sources staff choose from in the booking wizard (API bookings are made by the API only).
     *
     * @return array<int|string, string> value => label
     */
    public static function staffOptions(): array
    {
        return array_diff_key(self::options(), [self::Api->value => true]);
    }

    public function label(): string
    {
        return match ($this) {
            self::FrontDesk => __('Front desk'),
            self::Phone => __('Phone'),
            self::Email => __('Email'),
            self::WalkIn => __('Walk-in'),
            self::TravelAgent => __('Travel agent'),
            self::Corporate => __('Corporate'),
            self::Online => __('Online'),
            self::Api => __('API'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Online, self::Api => 'info',
            self::TravelAgent, self::Corporate => 'primary',
            default => 'secondary',
        };
    }
}
