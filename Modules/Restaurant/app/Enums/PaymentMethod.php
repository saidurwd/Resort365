<?php

namespace Modules\Restaurant\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * How a restaurant bill is paid (ARCHITECTURE §5.10.7). Room charge, city ledger and package come
 * with Step 3.7; complimentary settles a whole bill (CompBill).
 */
enum PaymentMethod: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Cash = 'cash';
    case Card = 'card';
    case Wallet = 'wallet';
    case BankTransfer = 'bank_transfer';
    case RoomCharge = 'room_charge';
    case CityLedger = 'city_ledger';
    case Package = 'package';
    case Complimentary = 'complimentary';

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Card => __('Card'),
            self::Wallet => __('Mobile wallet'),
            self::BankTransfer => __('Bank transfer'),
            self::RoomCharge => __('Charge to room'),
            self::CityLedger => __('City ledger'),
            self::Package => __('Meal plan'),
            self::Complimentary => __('Complimentary'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Cash => 'success',
            self::Card, self::Wallet, self::BankTransfer => 'primary',
            self::RoomCharge, self::CityLedger, self::Package => 'info',
            self::Complimentary => 'warning',
        };
    }

    /**
     * Methods a cashier takes on the payment screen now. TODO(step-3.7): room charge, city ledger, package.
     *
     * @return list<self>
     */
    public static function tenders(): array
    {
        return [self::Cash, self::Card, self::Wallet, self::BankTransfer];
    }
}
