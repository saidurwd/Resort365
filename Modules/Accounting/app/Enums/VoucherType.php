<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * Money received (income) or paid (expense) by a quick voucher.
 */
enum VoucherType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Income => __('Income'),
            self::Expense => __('Expense'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Income => 'success',
            self::Expense => 'danger',
        };
    }

    /** Document number type (IV-…, EV-…). */
    public function documentType(): string
    {
        return $this->value.'_voucher';
    }

    public function accountType(): AccountType
    {
        return match ($this) {
            self::Income => AccountType::Income,
            self::Expense => AccountType::Expense,
        };
    }
}
