<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Throwable;

/**
 * Reads a bank statement CSV (Step 4.4): a header row naming the columns (date, description, reference,
 * withdrawal and deposit, or one signed amount; balance optional), comma, semicolon or tab separated. Dates are
 * Y-m-d, d/m/Y, d-m-Y or d M Y; amounts may carry thousand separators, and brackets mean negative. Pure: the
 * lines read and the problems found, with the line number of each.
 */
class StatementCsv
{
    private const array ALIASES = [
        'date' => ['date', 'transaction date', 'txn date', 'value date', 'posting date'],
        'description' => ['description', 'details', 'narration', 'particulars', 'transaction details', 'memo'],
        'reference' => ['reference', 'ref', 'ref no', 'cheque no', 'cheque', 'chq no', 'transaction id'],
        'withdrawal' => ['withdrawal', 'withdrawals', 'debit', 'debits', 'paid out', 'money out'],
        'deposit' => ['deposit', 'deposits', 'credit', 'credits', 'paid in', 'money in'],
        'amount' => ['amount', 'net amount'],
        'balance' => ['balance', 'running balance', 'closing balance'],
    ];

    /**
     * @return array{lines: list<array{date: string, description: string, reference: string|null, withdrawal: string, deposit: string, balance: string|null}>, errors: list<string>}
     */
    public function parse(string $csv): array
    {
        $rows = $this->rows($csv);

        if ($rows === []) {
            return ['lines' => [], 'errors' => [__('The file is empty.')]];
        }

        $header = array_map(fn (string $name): string => mb_strtolower(trim($name, " \t\n\r\0\x0B\xEF\xBB\xBF\"")), array_shift($rows));
        $columns = [];

        foreach (self::ALIASES as $field => $names) {
            foreach ($header as $index => $name) {
                if (in_array($name, $names, true) && ! isset($columns[$field])) {
                    $columns[$field] = $index;
                }
            }
        }

        $missing = [];

        if (! isset($columns['date'])) {
            $missing[] = __('a date column');
        }

        if (! isset($columns['amount']) && (! isset($columns['withdrawal']) && ! isset($columns['deposit']))) {
            $missing[] = __('withdrawal and deposit columns (or an amount column)');
        }

        if ($missing !== []) {
            return ['lines' => [], 'errors' => [__('The header row needs :what.', ['what' => implode(' '.__('and').' ', $missing)])]];
        }

        $lines = [];
        $errors = [];

        foreach ($rows as $offset => $row) {
            $number = $offset + 2;
            $date = $this->date($row[$columns['date']] ?? '');
            $withdrawal = BigDecimal::zero();
            $deposit = BigDecimal::zero();

            try {
                if (isset($columns['amount']) && ! isset($columns['withdrawal']) && ! isset($columns['deposit'])) {
                    $amount = $this->amount($row[$columns['amount']] ?? '');
                    $amount->isNegative() ? $withdrawal = $amount->abs() : $deposit = $amount;
                } else {
                    $withdrawal = $this->amount($row[$columns['withdrawal'] ?? -1] ?? '')->abs();
                    $deposit = $this->amount($row[$columns['deposit'] ?? -1] ?? '')->abs();
                }

                $balance = isset($columns['balance']) && trim($row[$columns['balance']] ?? '') !== '' ? (string) $this->amount($row[$columns['balance']])->toScale(2) : null;
            } catch (Throwable) {
                $errors[] = __('Line :n: an amount is not a number.', ['n' => $number]);

                continue;
            }

            if ($date === null) {
                $errors[] = __('Line :n: the date ":date" is not valid (use 2026-10-31 or 31/10/2026).', ['n' => $number, 'date' => trim($row[$columns['date']] ?? '')]);
            } elseif ($withdrawal->isZero() && $deposit->isZero()) {
                $errors[] = __('Line :n: there is no amount.', ['n' => $number]);
            } elseif ($withdrawal->isPositive() && $deposit->isPositive()) {
                $errors[] = __('Line :n: use either a withdrawal or a deposit, not both.', ['n' => $number]);
            } else {
                $reference = isset($columns['reference']) ? trim($row[$columns['reference']] ?? '') : '';
                $lines[] = [
                    'date' => $date, 'description' => mb_substr(trim($row[$columns['description'] ?? -1] ?? ''), 0, 300), 'reference' => $reference !== '' ? mb_substr($reference, 0, 100) : null,
                    'withdrawal' => (string) $withdrawal->toScale(2), 'deposit' => (string) $deposit->toScale(2), 'balance' => $balance,
                ];
            }
        }

        if ($lines === [] && $errors === []) {
            $errors[] = __('The file has no statement lines.');
        }

        return ['lines' => $lines, 'errors' => $errors];
    }

    /**
     * @return list<list<string>>
     */
    private function rows(string $csv): array
    {
        $csv = trim(str_replace("\r\n", "\n", str_replace("\r", "\n", $csv)));

        if ($csv === '') {
            return [];
        }

        $first = strtok($csv, "\n") ?: '';
        $separator = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $rows = [];

        foreach (explode("\n", $csv) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = array_map(fn (?string $cell): string => (string) $cell, str_getcsv($line, $separator, '"', ''));
        }

        return $rows;
    }

    private function date(string $value): ?string
    {
        $value = trim($value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd M Y', 'd-M-Y', 'd.m.Y'] as $format) {
            try {
                $date = CarbonImmutable::createFromFormat('!'.$format, $value);
            } catch (Throwable) {
                continue;
            }

            if ($date instanceof CarbonImmutable && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    private function amount(string $value): BigDecimal
    {
        $value = trim($value);

        if ($value === '' || $value === '-') {
            return BigDecimal::zero();
        }

        $negative = str_starts_with($value, '(') && str_ends_with($value, ')');
        $clean = preg_replace('/[^0-9.\-]/', '', $value) ?? '';
        $number = BigDecimal::of($clean === '' ? 'x' : $clean);

        return $negative ? $number->abs()->negated() : $number;
    }
}
