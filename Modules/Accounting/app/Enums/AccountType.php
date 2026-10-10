<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The five kinds of ledger account (ARCHITECTURE §5.14). Assets and expenses grow with debits; liabilities,
 * equity and income with credits.
 */
enum AccountType: string implements HasLabelAndColor
{
    use EnumHelpers;

    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => __('Asset'),
            self::Liability => __('Liability'),
            self::Equity => __('Equity'),
            self::Income => __('Income'),
            self::Expense => __('Expense'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Asset => 'primary',
            self::Liability => 'warning',
            self::Equity => 'info',
            self::Income => 'success',
            self::Expense => 'danger',
        };
    }

    /**
     * Whether the account's normal balance is a debit.
     */
    public function isDebitNormal(): bool
    {
        return $this === self::Asset || $this === self::Expense;
    }
}
