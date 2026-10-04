<?php

namespace Modules\Reservation\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * A quote's life: saved (draft), emailed (sent), booked (accepted), turned down (declined) or past
 * its validity date (expired, worked out when it is shown or converted).
 */
enum QuoteStatus: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Sent => __('Sent'),
            self::Accepted => __('Booked'),
            self::Declined => __('Declined'),
            self::Expired => __('Expired'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'secondary',
            self::Sent => 'info',
            self::Accepted => 'success',
            self::Declined, self::Expired => 'danger',
        };
    }

    /**
     * Whether the quote may still be emailed, declined or turned into a booking.
     */
    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Sent], true);
    }
}
