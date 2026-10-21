<?php

/*
| Shared setup for the budget tests (plain functions): tenant "sunrise" with the chart of accounts, fiscal years 2025 and 2026,
| the departments FO and FBS, and helpers to post income and expense to the ledger on a date, at a property and department.
*/

use Modules\Accounting\Actions\CreateFiscalYear;
use Modules\Accounting\Actions\PostJournalEntry;
use Modules\Accounting\Actions\SaveJournalDraft;
use Modules\Accounting\Actions\SeedChartOfAccounts;
use Modules\Accounting\Models\Account;
use Modules\Accounting\Models\FiscalYear;
use Modules\Property\Models\Department;

require_once __DIR__.'/../../../Reservation/tests/Support/management-setup.php';

/**
 * @return array{y2025: FiscalYear, y2026: FiscalYear, fo: int, fbs: int}
 */
function bgSetup(): array
{
    return booking(function (): array {
        app(SeedChartOfAccounts::class)->handle();
        $y2025 = app(CreateFiscalYear::class)->handle('2025-01-01');
        $y2026 = app(CreateFiscalYear::class)->handle('2026-01-01');

        return [
            'y2025' => $y2025, 'y2026' => $y2026,
            'fo' => Department::factory()->create(['code' => 'FO', 'name' => 'Front Office'])->id, 'fbs' => Department::factory()->create(['code' => 'FBS', 'name' => 'F&B Service'])->id,
        ];
    });
}

function bgAccount(string $systemKey): int
{
    return (int) booking(fn (): int => Account::query()->where('system_key', $systemKey)->value('id'));
}

/**
 * Posts income (a credit to an income account) or expense (a debit to an expense account) against cash.
 */
function bgPost(string $systemKey, string $date, string $amount, ?int $department = null, ?int $propertyId = null, bool $reverse = false): void
{
    booking(function () use ($systemKey, $date, $amount, $department, $propertyId, $reverse): void {
        $account = Account::query()->where('system_key', $systemKey)->firstOrFail();
        $income = $account->type->value === 'income';
        $property = $propertyId ?? bookingIds()['property'];
        $side = $income !== $reverse ? 'credit' : 'debit';
        $other = $side === 'credit' ? 'debit' : 'credit';
        $draft = app(SaveJournalDraft::class)->handle(null, ['entry_date' => $date, 'description' => 'Budget test'], [
            ['account_id' => Account::query()->where('system_key', 'cash_on_hand')->value('id'), $other => $amount, 'property_id' => $property],
            ['account_id' => $account->id, $side => $amount, 'property_id' => $property, 'department_id' => $department],
        ]);
        app(PostJournalEntry::class)->handle($draft);
    });
}
