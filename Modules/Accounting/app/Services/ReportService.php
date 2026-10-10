<?php

namespace Modules\Accounting\Services;

use Carbon\CarbonImmutable;
use Modules\Accounting\DTOs\AccountActivity;
use Modules\Accounting\DTOs\Report;
use Modules\Accounting\DTOs\ReportFilter;
use Modules\Accounting\Enums\AccountType;
use Modules\Accounting\Enums\JournalStatus;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\JournalLine;
use Modules\Accounting\Services\Statements\CashFlowBuilder;
use Modules\Accounting\Services\Statements\DepartmentalReport;
use Modules\Accounting\Services\Statements\LedgerAging;
use Modules\Accounting\Services\Statements\StatementTree;
use Modules\Accounting\Services\Statements\TrialBalanceBuilder;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Property\Contracts\PropertyDirectory;

/**
 * The financial reports of Step 4.5, each as a finished `Report` for the screen and the exports: trial balance,
 * general ledger, profit and loss (per property and consolidated), balance sheet, cash flow, USALI departmental
 * report and aging. Every figure of a statement links to the ledger lines behind it (drill-down).
 */
class ReportService
{
    private const array UNDISTRIBUTED = [
        'admin' => 'Administrative and general', 'sales' => 'Sales and marketing', 'pom' => 'Property operations and maintenance', 'utilities' => 'Utilities', 'other' => 'Other',
    ];

    public function __construct(
        private readonly LedgerQuery $ledger,
        private readonly StatementTree $tree,
        private readonly TrialBalanceBuilder $trialBalance,
        private readonly CashFlowBuilder $cashFlow,
        private readonly DepartmentalReport $departmental,
        private readonly LedgerAging $aging,
        private readonly PropertyDirectory $properties,
        private readonly GuestLookup $guests,
    ) {}

    public function trialBalance(ReportFilter $filter): Report
    {
        $built = $this->trialBalance->build($this->ledger->activity($filter));
        $rows = [];

        foreach ($built['rows'] as $row) {
            $account = $row['account'];
            $link = fn (): string => $this->ledgerUrl($account->id, $filter);
            $rows[] = ['cells' => [$account->code.' · '.$account->name, ...array_map($this->money(...), [$row['opening_debit'], $row['opening_credit'], $row['debit'], $row['credit'], $row['closing_debit'], $row['closing_credit']])],
                'links' => array_fill_keys(range(1, 6), $link())];
        }

        $totals = $built['totals'];
        $rows[] = ['cells' => [__('Total'), ...array_map($this->money(...), [$totals['opening_debit'], $totals['opening_credit'], $totals['debit'], $totals['credit'], $totals['closing_debit'], $totals['closing_credit']])], 'bold' => true];

        return new Report(__('Trial balance'), $this->period($filter), [__('Account'), __('Opening debit'), __('Opening credit'), __('Debit'), __('Credit'), __('Closing debit'), __('Closing credit')], $rows,
            [$built['balanced'] ? __('The trial balance balances: debits equal credits.') : __('The trial balance does NOT balance: debits and credits differ.')]);
    }

    public function generalLedger(ReportFilter $filter, int $accountId): Report
    {
        $account = Account::query()->find($accountId) ?? throw new AccountingRuleViolated(__('Choose an account.'));
        $sign = $account->type->isDebitNormal() ? 1 : -1;
        $balance = $this->signed($this->ledger->balanceBefore($filter, $accountId), $sign);
        $rows = [['cells' => [__('Opening balance'), '', '', '', '', $this->money($balance)], 'bold' => true]];
        $debit = '0.00';
        $credit = '0.00';

        foreach ($this->ledger->lines($filter, $accountId) as $line) {
            $balance = bcadd($balance, $this->signed(bcsub((string) $line->debit, (string) $line->credit, 2), $sign), 2);
            $debit = bcadd($debit, (string) $line->debit, 2);
            $credit = bcadd($credit, (string) $line->credit, 2);
            $url = route('accounting.journals.show', $line->entry_id);
            $rows[] = ['cells' => [CarbonImmutable::parse($line->entry_date)->format('d M Y'), (string) $line->entry_no, trim($line->description.($line->line_description ? ' · '.$line->line_description : '')),
                (float) $line->debit > 0 ? $this->money((string) $line->debit) : '', (float) $line->credit > 0 ? $this->money((string) $line->credit) : '', $this->money($balance)], 'links' => [1 => $url, 2 => $url]];
        }

        $rows[] = ['cells' => [__('Closing balance'), '', '', $this->money($debit), $this->money($credit), $this->money($balance)], 'bold' => true];

        return new Report(__('General ledger'), $account->label().' · '.$this->period($filter), [__('Date'), __('Entry'), __('Description'), __('Debit'), __('Credit'), __('Balance')], $rows);
    }

    public function profitAndLoss(ReportFilter $filter): Report
    {
        $consolidated = $filter->property === null;
        $accounts = $this->ledger->activity($filter, $consolidated);
        $columns = ['total'];

        if ($consolidated) {
            $used = [];

            foreach ($accounts as $account) {
                if ($account->type === AccountType::Income || $account->type === AccountType::Expense) {
                    $used += array_fill_keys(array_keys($account->columns), true);
                }
            }

            $columns = [...array_values(array_filter(array_map(strval(...), array_keys($used)), fn (string $key): bool => $key !== 'none')), ...(isset($used['none']) ? ['none'] : []), 'total'];
            $columns = count($columns) > 2 ? $columns : ['total'];
        }

        $names = $this->propertyNames();
        $header = array_map(fn (string $column): string => match ($column) {
            'total' => count($columns) > 1 ? __('Consolidated') : __('Total'), 'none' => __('Not by property'), default => $names[(int) $column] ?? '#'.$column
        }, $columns);
        $rows = [];
        $totals = [];

        foreach ([[AccountType::Income, -1, __('Income')], [AccountType::Expense, 1, __('Expenses')]] as [$type, $sign, $title]) {
            $section = array_values(array_filter($accounts, fn (AccountActivity $account): bool => $account->type === $type));
            $amounts = [];

            foreach ($section as $account) {
                foreach ($columns as $column) {
                    $net = $column === 'total' ? $account->net() : ($account->columns[$column] ?? '0.00');
                    $amounts[$account->id][$column] = $this->signed($net, $sign);
                }
            }

            $tree = $this->tree->rows($section, $amounts, $columns);
            $rows[] = ['cells' => [$title, ...array_fill(0, count($columns), '')], 'bold' => true];

            foreach ($tree as $row) {
                $rows[] = $this->statementRow($row, $columns, $filter);
            }

            $totals[$type->value] = $this->tree->totals($tree, $columns);
            $rows[] = ['cells' => [__('Total :section', ['section' => mb_strtolower($title)]), ...array_map(fn (string $column): string => $this->money($totals[$type->value][$column]), $columns)], 'bold' => true];
        }

        $rows[] = ['cells' => [__('Net result'), ...array_map(fn (string $column): string => $this->money(bcsub($totals['income'][$column], $totals['expense'][$column], 2)), $columns)], 'bold' => true];

        return new Report(__('Profit and loss'), $this->period($filter).$this->propertyLabel($filter), [__('Account'), ...$header], $rows);
    }

    public function balanceSheet(ReportFilter $filter): Report
    {
        $all = new ReportFilter('0001-01-01', $filter->to, $filter->property);
        $accounts = $this->ledger->activity($all);
        $rows = [];
        $totals = [];

        foreach ([[AccountType::Asset, 1, __('Assets')], [AccountType::Liability, -1, __('Liabilities')], [AccountType::Equity, -1, __('Equity')]] as [$type, $sign, $title]) {
            $section = array_values(array_filter($accounts, fn (AccountActivity $account): bool => $account->type === $type));
            $amounts = [];

            foreach ($section as $account) {
                $amounts[$account->id]['total'] = $this->signed($account->closing(), $sign);
            }

            $tree = $this->tree->rows($section, $amounts, ['total']);
            $rows[] = ['cells' => [$title, ''], 'bold' => true];

            foreach ($tree as $row) {
                $rows[] = $this->statementRow($row, ['total'], new ReportFilter(CarbonImmutable::parse($filter->to)->startOfYear()->toDateString(), $filter->to, $filter->property));
            }

            $total = $this->tree->totals($tree, ['total'])['total'];

            if ($type === AccountType::Equity) {
                $result = '0.00';

                foreach ($accounts as $account) {
                    if (! $account->isGroup && ($account->type === AccountType::Income || $account->type === AccountType::Expense)) {
                        $result = bcsub($result, $account->closing(), 2);
                    }
                }

                $rows[] = ['cells' => [__('Result to date (not yet closed to retained earnings)'), $this->money($result)], 'indent' => 1];
                $total = bcadd($total, $result, 2);
            }

            $totals[$type->value] = $total;
            $rows[] = ['cells' => [__('Total :section', ['section' => mb_strtolower($title)]), $this->money($total)], 'bold' => true];
        }

        $other = bcadd($totals['liability'], $totals['equity'], 2);
        $rows[] = ['cells' => [__('Liabilities and equity'), $this->money($other)], 'bold' => true];

        return new Report(__('Balance sheet'), __('As at :date', ['date' => CarbonImmutable::parse($filter->to)->format('d M Y')]).$this->propertyLabel($filter), [__('Account'), __('Balance')], $rows,
            [bccomp($totals['asset'], $other, 2) === 0 ? __('The balance sheet balances: assets equal liabilities and equity.') : __('The balance sheet does NOT balance: assets and liabilities plus equity differ by :amount.', ['amount' => bcsub($totals['asset'], $other, 2)])]);
    }

    public function cashFlow(ReportFilter $filter): Report
    {
        $built = $this->cashFlow->build($this->ledger->activity($filter));
        $rows = [['cells' => [__('Net result of the period'), $this->money($built['result'])], 'bold' => true]];

        foreach ([['operating', __('Operating activities'), __('changes in working capital')], ['investing', __('Investing activities'), null], ['financing', __('Financing activities'), null]] as [$key, $title]) {
            $rows[] = ['cells' => [$title, ''], 'bold' => true];

            foreach ($built[$key] as $row) {
                $rows[] = ['cells' => [$row['account']->code.' · '.$row['account']->name, $this->money($row['amount'])], 'indent' => 1, 'links' => [1 => $this->ledgerUrl($row['account']->id, $filter)]];
            }

            $rows[] = ['cells' => [__('Cash from :activity', ['activity' => mb_strtolower($title)]), $this->money($built['totals'][$key])], 'bold' => true];
        }

        $rows[] = ['cells' => [__('Change in cash'), $this->money($built['totals']['change'])], 'bold' => true];
        $rows[] = ['cells' => [__('Cash at the start'), $this->money($built['opening_cash'])]];
        $rows[] = ['cells' => [__('Cash at the end'), $this->money($built['closing_cash'])], 'bold' => true];

        return new Report(__('Cash flow'), $this->period($filter).$this->propertyLabel($filter), [__('Item'), __('Amount')], $rows,
            [bccomp($built['difference'], '0', 2) === 0 ? __('The cash flow agrees with the change in the cash and bank accounts.') : __('The cash flow differs from the change in cash by :amount.', ['amount' => $built['difference']])]);
    }

    public function departmental(ReportFilter $filter): Report
    {
        $built = $this->departmental->build($this->ledger->activity($filter));
        $labels = ['rooms' => __('Rooms'), 'fnb' => __('Food and beverage'), 'other_operated' => __('Other operated departments')];
        $rows = [];

        foreach ($built['departments'] as $key => $row) {
            $rows[] = ['cells' => [$labels[$key], $this->money($row['revenue']), $this->money($row['expense']), $this->money($row['profit'])]];
        }

        $revenue = '0.00';
        $expense = '0.00';
        $profit = '0.00';

        foreach ($built['departments'] as $row) {
            $revenue = bcadd($revenue, $row['revenue'], 2);
            $expense = bcadd($expense, $row['expense'], 2);
            $profit = bcadd($profit, $row['profit'], 2);
        }

        $rows[] = ['cells' => [__('Operated departments'), $this->money($revenue), $this->money($expense), $this->money($profit)], 'bold' => true];
        $rows[] = ['cells' => [__('Undistributed expenses'), '', '', ''], 'bold' => true];

        foreach ($built['undistributed'] as $key => $amount) {
            $rows[] = ['cells' => [self::UNDISTRIBUTED[(string) $key] ?? ucfirst(str_replace('_', ' ', (string) $key)), '', $this->money($amount), $this->money(bcsub('0', $amount, 2))], 'indent' => 1];
        }

        $rows[] = ['cells' => [__('Gross operating profit'), $this->money($built['income_total']), '', $this->money($built['gross_operating_profit'])], 'bold' => true];

        return new Report(__('USALI departmental report'), $this->period($filter).$this->propertyLabel($filter), [__('Department'), __('Revenue'), __('Expense'), __('Profit')], $rows);
    }

    public function aging(bool $receivable, string $asOf, ?string $property = null): Report
    {
        $key = $receivable ? 'city_ledger' : 'accounts_payable';
        $accountId = Account::query()->where('system_key', $key)->value('id');
        $movements = [];

        if ($accountId !== null) {
            $lines = JournalLine::query()->where('account_id', $accountId)->with('entry')
                ->when($property !== null && ctype_digit($property), fn ($query) => $query->where('property_id', (int) $property))
                ->whereHas('entry', fn ($query) => $query->whereIn('status', [JournalStatus::Posted->value, JournalStatus::Reversed->value])->where('entry_date', '<=', $asOf))->get();

            foreach ($lines as $line) {
                $amount = $receivable ? bcsub((string) $line->debit, (string) $line->credit, 2) : bcsub((string) $line->credit, (string) $line->debit, 2);
                $movements[] = ['party' => $this->partyLabel($line->party_type?->value, $line->party_id), 'date' => $line->entry->entry_date->toDateString(), 'amount' => $amount];
            }
        }

        $aged = $this->aging->build($movements, $asOf);
        $rows = [];
        $totals = array_fill_keys([...LedgerAging::BUCKETS, 'credit', 'total'], '0.00');

        foreach ($aged as $party => $row) {
            $rows[] = ['cells' => [$party, ...array_map(fn (string $bucket): string => $this->money($row[$bucket]), [...LedgerAging::BUCKETS, 'credit', 'total'])]];

            foreach ($totals as $bucket => $sum) {
                $totals[$bucket] = bcadd($sum, $row[$bucket], 2);
            }
        }

        $rows[] = ['cells' => [__('Total'), ...array_map(fn (string $bucket): string => $this->money($totals[$bucket]), [...LedgerAging::BUCKETS, 'credit', 'total'])], 'bold' => true];

        return new Report($receivable ? __('Receivables aging (city ledger)') : __('Payables aging'), __('As at :date', ['date' => CarbonImmutable::parse($asOf)->format('d M Y')]),
            [$receivable ? __('Company') : __('Vendor'), __('0–30 days'), __('31–60 days'), __('61–90 days'), __('Over 90 days'), __('Paid in advance'), __('Balance')], $rows);
    }

    /**
     * @param  array{account: AccountActivity, depth: int, amounts: array<string, string>}  $row
     * @param  list<string>  $columns
     * @return array{cells: list<string>, bold?: bool, indent?: int, links?: array<int, string>}
     */
    private function statementRow(array $row, array $columns, ReportFilter $filter): array
    {
        $account = $row['account'];
        $links = [];

        if (! $account->isGroup) {
            foreach ($columns as $index => $column) {
                $property = $column === 'total' ? $filter->property : $column;
                $links[$index + 1] = $this->ledgerUrl($account->id, new ReportFilter($filter->from, $filter->to, $property, $filter->departmentId));
            }
        }

        return [
            'cells' => [$account->code.' · '.$account->name, ...array_map(fn (string $column): string => $this->money($row['amounts'][$column]), $columns)],
            'bold' => $account->isGroup, 'indent' => $row['depth'] + 1, 'links' => $links,
        ];
    }

    private function ledgerUrl(int $accountId, ReportFilter $filter): string
    {
        return route('accounting.reports.show', ['report' => 'ledger', 'account' => $accountId, 'from' => $filter->from, 'to' => $filter->to, 'property' => $filter->property, 'department' => $filter->departmentId]);
    }

    private function partyLabel(?string $type, ?int $id): string
    {
        if ($type === null || $id === null) {
            return __('Unassigned');
        }

        if ($type === 'company') {
            return $this->guests->findCompany($id)->name ?? __('Company #:id', ['id' => $id]);
        }

        return ucfirst(str_replace('_', ' ', $type)).' #'.$id;
    }

    /**
     * @return array<int, string>
     */
    private function propertyNames(): array
    {
        $names = [];

        foreach ($this->properties->all() as $property) {
            $names[$property->id] = $property->name;
        }

        return $names;
    }

    private function propertyLabel(ReportFilter $filter): string
    {
        return match (true) {
            $filter->withoutProperty() => ' · '.__('Not by property'),
            $filter->propertyId() !== null => ' · '.($this->propertyNames()[$filter->propertyId()] ?? ''),
            default => '',
        };
    }

    private function period(ReportFilter $filter): string
    {
        return CarbonImmutable::parse($filter->from)->format('d M Y').' – '.CarbonImmutable::parse($filter->to)->format('d M Y');
    }

    private function signed(string $net, int $sign): string
    {
        $value = $sign < 0 ? bcsub('0', $net, 2) : bcadd($net, '0', 2);

        return bccomp($value, '0', 2) === 0 ? '0.00' : $value;
    }

    private function money(string $value): string
    {
        return number_format((float) $value, 2);
    }
}
