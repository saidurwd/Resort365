<?php

namespace Modules\Accounting\Actions;

use App\Support\Actions\Action;
use Brick\Math\BigDecimal;
use Modules\Accounting\Enums\BankAccountKind;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Accounting\Models\BankAccount;
use Modules\Accounting\Models\BankStatement;
use Modules\Accounting\Models\BankStatementLine;
use Modules\Accounting\Services\StatementCsv;

/**
 * Imports a bank statement CSV (Step 4.4), all or nothing. A line already imported (same date, text, amount and
 * reference, counted by how often it occurs in the file) is skipped, so the same file twice adds nothing. The
 * closing balance is the file's last balance, or the one given.
 */
class ImportStatement extends Action
{
    public function __construct(private readonly StatementCsv $csv) {}

    /**
     * @return array{statement: BankStatement, imported: int, skipped: int}
     *
     * @throws AccountingRuleViolated
     */
    public function handle(BankAccount $bank, string $fileName, string $contents, ?string $closingBalance = null, ?int $userId = null): array
    {
        if ($bank->kind !== BankAccountKind::Bank || ! $bank->is_active) {
            throw new AccountingRuleViolated(__('Statements are imported for active bank accounts.'));
        }

        $parsed = $this->csv->parse($contents);

        if ($parsed['errors'] !== []) {
            throw new AccountingRuleViolated(implode(' ', array_slice($parsed['errors'], 0, 5)).(count($parsed['errors']) > 5 ? ' '.__('And :n more.', ['n' => count($parsed['errors']) - 5]) : ''));
        }

        $closing = $closingBalance !== null && trim($closingBalance) !== '' ? (string) BigDecimal::of($closingBalance)->toScale(2) : $this->lastBalance($parsed['lines']);

        if ($closing === null) {
            throw new AccountingRuleViolated(__('The file has no balance column: enter the statement\'s closing balance.'));
        }

        return $this->transaction(function () use ($bank, $fileName, $parsed, $closing, $userId): array {
            $seen = [];
            $fresh = [];

            foreach ($parsed['lines'] as $line) {
                $key = implode('|', [$line['date'], $line['description'], $line['withdrawal'], $line['deposit'], (string) $line['reference']]);
                $seen[$key] = ($seen[$key] ?? 0) + 1;
                $fingerprint = sha1($key.'|'.$seen[$key]);

                if (! BankStatementLine::query()->where('bank_account_id', $bank->id)->where('fingerprint', $fingerprint)->exists()) {
                    $fresh[] = [...$line, 'fingerprint' => $fingerprint];
                }
            }

            if ($fresh === []) {
                throw new AccountingRuleViolated(__('Every line of this file was imported before: nothing new.'));
            }

            $dates = array_column($fresh, 'date');
            $statement = BankStatement::query()->create([
                'bank_account_id' => $bank->id, 'file_name' => mb_substr($fileName, 0, 190), 'statement_from' => min($dates), 'statement_to' => max($dates),
                'closing_balance' => $closing, 'line_count' => count($fresh), 'imported_by' => $userId,
            ]);

            foreach ($fresh as $index => $line) {
                BankStatementLine::query()->create([
                    'bank_statement_id' => $statement->id, 'bank_account_id' => $bank->id, 'line_no' => $index + 1, 'txn_date' => $line['date'], 'description' => $line['description'],
                    'reference' => $line['reference'], 'withdrawal' => $line['withdrawal'], 'deposit' => $line['deposit'], 'balance' => $line['balance'], 'fingerprint' => $line['fingerprint'],
                ]);
            }

            return ['statement' => $statement, 'imported' => count($fresh), 'skipped' => count($parsed['lines']) - count($fresh)];
        });
    }

    /**
     * @param  list<array{date: string, balance: string|null}>  $lines
     */
    private function lastBalance(array $lines): ?string
    {
        for ($i = count($lines) - 1; $i >= 0; $i--) {
            if ($lines[$i]['balance'] !== null) {
                return $lines[$i]['balance'];
            }
        }

        return null;
    }
}
