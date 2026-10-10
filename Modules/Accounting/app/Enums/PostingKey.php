<?php

namespace Modules\Accounting\Enums;

use App\Support\Enums\EnumHelpers;
use App\Support\Enums\HasLabelAndColor;

/**
 * The fixed accounts the automatic postings use (ARCHITECTURE §7.1). Each has a default: the chart
 * account carrying the same system key, unless the tenant maps another. Payment methods
 * (`method:cash`) and charge codes (`charge:SPA`) are mapped the same way, see AccountResolver.
 */
enum PostingKey: string implements HasLabelAndColor
{
    use EnumHelpers;

    case GuestLedger = 'guest_ledger';
    case CityLedger = 'city_ledger';
    case CustomerAdvances = 'customer_advances';
    case CancellationRevenue = 'cancellation_revenue';
    case ServiceChargePayable = 'service_charge_payable';
    case VatPayable = 'vat_payable';
    case InputVat = 'input_vat';
    case TipsPayable = 'tips_payable';
    case CashShort = 'cash_short_expense';
    case CashOver = 'cash_over_income';

    public function label(): string
    {
        return match ($this) {
            self::GuestLedger => __('Guest ledger (receivable from in-house guests)'),
            self::CityLedger => __('City ledger (receivable from companies)'),
            self::CustomerAdvances => __('Customer advances (deposits held)'),
            self::CancellationRevenue => __('Cancellation and no-show revenue'),
            self::ServiceChargePayable => __('Service charge payable'),
            self::VatPayable => __('VAT and other taxes payable'),
            self::InputVat => __('Input VAT (recoverable, on expenses)'),
            self::TipsPayable => __('Tips payable (to staff)'),
            self::CashShort => __('Cash short (expense)'),
            self::CashOver => __('Cash over (income)'),
        };
    }

    public function color(): string
    {
        return 'secondary';
    }
}
