<?php

namespace Modules\Billing\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a payment was made (ARCHITECTURE §5.9): the manual methods staff record, and `online` for payments made through a payment
 * gateway (Step 8.3; the gateway and its transaction are on the payment).
 */
enum PaymentMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Cash = 'cash';
    case Card = 'card';
    case BankTransfer = 'bank_transfer';
    case MobileWallet = 'mobile_wallet';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::BankTransfer => __('Bank transfer'),
            self::MobileWallet => __('Mobile wallet'),
            self::Online => __('Online payment'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Card => 'primary',
            self::BankTransfer => 'info',
            self::MobileWallet => 'warning',
            self::Online => 'secondary',
        };
    }

    /**
     * The methods staff record by hand (online payments come only from a gateway).
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Cash, self::Card, self::BankTransfer, self::MobileWallet];
    }

    /**
     * value => label of the manual methods, for a select.
     *
     * @return array<string, string>
     */
    public static function manualOptions(): array
    {
        $options = [];

        foreach (self::manual() as $method) {
            $options[$method->value] = $method->label();
        }

        return $options;
    }

    /**
     * Whether the method usually has a reference (card slip, transaction id).
     */
    public function needsReference(): bool
    {
        return $this !== self::Cash;
    }
}
