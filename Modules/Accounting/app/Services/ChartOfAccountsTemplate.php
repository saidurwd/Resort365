<?php

namespace Modules\Accounting\Services;

use Modules\Accounting\Enums\AccountType;

/**
 * The USALI-aligned starting chart of accounts (ARCHITECTURE §5.14): headers for each type, then the
 * accounts a resort uses, tagged with the department of the Uniform System of Accounts for the Lodging
 * Industry where they belong. Accounts automatic postings use carry a system key (Step 4.2). Fully
 * editable once seeded.
 */
class ChartOfAccountsTemplate
{
    /**
     * code => [name, type, parent code, group?, system key, USALI department]
     *
     * @return array<int|string, array{string, AccountType, string|null, bool, string|null, string|null}>
     */
    public function accounts(): array
    {
        $A = AccountType::Asset;
        $L = AccountType::Liability;
        $E = AccountType::Equity;
        $I = AccountType::Income;
        $X = AccountType::Expense;

        return [
            '1000' => ['Assets', $A, null, true, null, null],
            '1100' => ['Cash and bank', $A, '1000', true, null, null],
            '1110' => ['Cash on hand', $A, '1100', false, 'cash_on_hand', null],
            '1120' => ['Bank accounts', $A, '1100', false, 'bank', null],
            '1130' => ['Card clearing', $A, '1100', false, 'card_clearing', null],
            '1140' => ['Mobile wallet clearing', $A, '1100', false, 'wallet_clearing', null],
            '1150' => ['Cheques in hand', $A, '1100', false, 'cheques_in_hand', null],
            '1200' => ['Receivables', $A, '1000', true, null, null],
            '1210' => ['Guest ledger (AR)', $A, '1200', false, 'guest_ledger', null],
            '1220' => ['City ledger (AR)', $A, '1200', false, 'city_ledger', null],
            '1230' => ['Employee loans receivable', $A, '1200', false, 'loan_receivable', null],
            '1240' => ['Other receivables', $A, '1200', false, null, null],
            '1300' => ['Inventory', $A, '1000', true, null, null],
            '1310' => ['Food inventory', $A, '1300', false, 'inventory_food', 'fnb'],
            '1320' => ['Beverage inventory', $A, '1300', false, 'inventory_beverage', 'fnb'],
            '1330' => ['Housekeeping supplies inventory', $A, '1300', false, 'inventory_housekeeping', 'rooms'],
            '1340' => ['Maintenance and engineering stock', $A, '1300', false, 'inventory_maintenance', 'pom'],
            '1350' => ['Operating supplies inventory', $A, '1300', false, 'inventory_operating', null],
            '1400' => ['Taxes and prepayments', $A, '1000', true, null, null],
            '1410' => ['Input VAT', $A, '1400', false, 'input_vat', null],
            '1420' => ['Prepaid expenses', $A, '1400', false, null, null],
            '1500' => ['Property and equipment', $A, '1000', true, null, null],
            '1510' => ['Buildings and cottages', $A, '1500', false, null, null],
            '1520' => ['Furniture, fixtures and equipment', $A, '1500', false, null, null],
            '1590' => ['Accumulated depreciation', $A, '1500', false, null, null],

            '2000' => ['Liabilities', $L, null, true, null, null],
            '2100' => ['Guest deposits', $L, '2000', true, null, null],
            '2110' => ['Customer advances', $L, '2100', false, 'customer_advances', null],
            '2200' => ['Payables', $L, '2000', true, null, null],
            '2210' => ['Accounts payable', $L, '2200', false, 'accounts_payable', null],
            '2220' => ['Goods received not invoiced (GRNI)', $L, '2200', false, 'grni', null],
            '2300' => ['Taxes and service charge payable', $L, '2000', true, null, null],
            '2310' => ['Service charge payable', $L, '2300', false, 'service_charge_payable', null],
            '2320' => ['VAT payable', $L, '2300', false, 'vat_payable', null],
            '2330' => ['Other taxes payable', $L, '2300', false, null, null],
            '2340' => ['Tips payable', $L, '2300', false, 'tips_payable', null],
            '2400' => ['Payroll liabilities', $L, '2000', true, null, null],
            '2410' => ['Salaries payable', $L, '2400', false, 'salaries_payable', null],
            '2420' => ['Income tax withheld payable', $L, '2400', false, 'tax_payable', null],
            '2430' => ['Provident fund payable', $L, '2400', false, 'pf_payable', null],
            '2500' => ['Loans and borrowings', $L, '2000', true, null, null],
            '2510' => ['Bank loans', $L, '2500', false, null, null],

            '3000' => ['Equity', $E, null, true, null, null],
            '3100' => ['Owner\'s capital', $E, '3000', false, 'owners_capital', null],
            '3200' => ['Retained earnings', $E, '3000', false, 'retained_earnings', null],
            '3300' => ['Owner\'s drawings', $E, '3000', false, null, null],

            '4000' => ['Revenue', $I, null, true, null, null],
            '4100' => ['Rooms', $I, '4000', true, null, 'rooms'],
            '4110' => ['Room revenue', $I, '4100', false, 'room_revenue', 'rooms'],
            '4120' => ['Cancellation and no-show revenue', $I, '4100', false, 'cancellation_revenue', 'rooms'],
            '4130' => ['Extra bed and extra person revenue', $I, '4100', false, 'extra_bed_revenue', 'rooms'],
            '4200' => ['Food and beverage', $I, '4000', true, null, 'fnb'],
            '4210' => ['Food revenue', $I, '4200', false, 'food_revenue', 'fnb'],
            '4220' => ['Beverage revenue', $I, '4200', false, 'beverage_revenue', 'fnb'],
            '4230' => ['Banquet and event revenue', $I, '4200', false, null, 'fnb'],
            '4300' => ['Other operated departments', $I, '4000', true, null, 'other_operated'],
            '4310' => ['Spa and wellness revenue', $I, '4300', false, 'spa_revenue', 'other_operated'],
            '4320' => ['Laundry revenue', $I, '4300', false, 'laundry_revenue', 'other_operated'],
            '4330' => ['Transport and transfers revenue', $I, '4300', false, 'transport_revenue', 'other_operated'],
            '4340' => ['Tours and activities revenue', $I, '4300', false, 'tours_revenue', 'other_operated'],
            '4350' => ['Miscellaneous guest services revenue', $I, '4300', false, 'misc_revenue', 'other_operated'],
            '4900' => ['Other income', $I, '4000', true, null, null],
            '4910' => ['Rental and concession income', $I, '4900', false, null, null],
            '4920' => ['Interest income', $I, '4900', false, null, null],
            '4930' => ['Cash over', $I, '4900', false, 'cash_over_income', null],
            '4940' => ['Foreign exchange gain', $I, '4900', false, 'fx_gain', null],

            '5000' => ['Cost of sales', $X, null, true, null, null],
            '5100' => ['Food and beverage cost of sales', $X, '5000', true, null, 'fnb'],
            '5110' => ['Food cost of sales', $X, '5100', false, 'food_cost', 'fnb'],
            '5120' => ['Beverage cost of sales', $X, '5100', false, 'beverage_cost', 'fnb'],
            '5130' => ['Food wastage', $X, '5100', false, 'food_wastage', 'fnb'],
            '5140' => ['Staff meals and complimentary', $X, '5100', false, 'staff_meal_expense', 'fnb'],
            '5150' => ['Inventory shrinkage', $X, '5100', false, 'inventory_shrinkage', 'fnb'],
            '5200' => ['Departmental expenses', $X, '5000', true, null, null],
            '5210' => ['Rooms: salaries and wages', $X, '5200', false, null, 'rooms'],
            '5220' => ['Rooms: housekeeping supplies and laundry', $X, '5200', false, 'housekeeping_supplies_expense', 'rooms'],
            '5230' => ['Rooms: guest supplies and amenities', $X, '5200', false, null, 'rooms'],
            '5240' => ['Food and beverage: salaries and wages', $X, '5200', false, null, 'fnb'],
            '5250' => ['Food and beverage: operating supplies', $X, '5200', false, null, 'fnb'],
            '5260' => ['Other operated departments: expenses', $X, '5200', false, null, 'other_operated'],

            '6000' => ['Undistributed operating expenses', $X, null, true, null, null],
            '6100' => ['Administrative and general', $X, '6000', true, null, 'admin'],
            '6110' => ['Salaries and wages', $X, '6100', false, 'salaries_expense', 'admin'],
            '6120' => ['Employee benefits and provident fund', $X, '6100', false, null, 'admin'],
            '6130' => ['Office and communication', $X, '6100', false, null, 'admin'],
            '6140' => ['Professional fees', $X, '6100', false, null, 'admin'],
            '6150' => ['Bank and card charges', $X, '6100', false, 'bank_charges', 'admin'],
            '6160' => ['Cash short', $X, '6100', false, 'cash_short_expense', 'admin'],
            '6170' => ['Foreign exchange loss', $X, '6100', false, 'fx_loss', 'admin'],
            '6200' => ['Sales and marketing', $X, '6000', true, null, 'sales'],
            '6210' => ['Advertising and promotion', $X, '6200', false, null, 'sales'],
            '6220' => ['Travel agent and OTA commissions', $X, '6200', false, null, 'sales'],
            '6300' => ['Utilities', $X, '6000', true, null, 'utilities'],
            '6310' => ['Electricity', $X, '6300', false, null, 'utilities'],
            '6320' => ['Water', $X, '6300', false, null, 'utilities'],
            '6330' => ['Fuel and gas', $X, '6300', false, null, 'utilities'],
            '6400' => ['Property operation and maintenance', $X, '6000', true, null, 'pom'],
            '6410' => ['Repairs and maintenance', $X, '6400', false, null, 'pom'],
            '6420' => ['Grounds and landscaping', $X, '6400', false, null, 'pom'],
            '6430' => ['Engineering supplies', $X, '6400', false, 'engineering_supplies_expense', 'pom'],
            '6500' => ['Fixed charges', $X, '6000', true, null, null],
            '6510' => ['Insurance', $X, '6500', false, null, null],
            '6520' => ['Rent and licences', $X, '6500', false, null, null],
            '6530' => ['Depreciation', $X, '6500', false, null, null],
            '6540' => ['Interest expense', $X, '6500', false, null, null],
        ];
    }
}
