<?php

use Modules\Accounting\Services\StatementCsv;
use Tests\TestCase;

uses(TestCase::class);

it('reads withdrawal and deposit columns, thousand separators and several date formats', function (): void {
    $csv = "Date,Description,Reference,Withdrawal,Deposit,Balance\n2026-10-01,Opening,,,\"2,450,000.00\",\"2,450,000.00\"\n02/10/2026,\"Fuel, generator\",CHQ-1,\"8,000.00\",,2442000.00\n";
    $read = (new StatementCsv)->parse($csv);

    expect($read['errors'])->toBe([])
        ->and($read['lines'][0])->toBe(['date' => '2026-10-01', 'description' => 'Opening', 'reference' => null, 'withdrawal' => '0.00', 'deposit' => '2450000.00', 'balance' => '2450000.00'])
        ->and($read['lines'][1])->toBe(['date' => '2026-10-02', 'description' => 'Fuel, generator', 'reference' => 'CHQ-1', 'withdrawal' => '8000.00', 'deposit' => '0.00', 'balance' => '2442000.00']);
});

it('reads one signed amount column, with brackets as negative', function (): void {
    $read = (new StatementCsv)->parse("date;details;amount\n2026-10-01;Deposit;1000.50\n2026-10-02;Charge;(25.00)\n2026-10-03;Other;-10\n");

    expect($read['errors'])->toBe([])
        ->and(array_map(fn (array $line): array => [$line['withdrawal'], $line['deposit']], $read['lines']))->toBe([['0.00', '1000.50'], ['25.00', '0.00'], ['10.00', '0.00']]);
});

it('accepts header names from other banks', function (): void {
    $read = (new StatementCsv)->parse("Transaction Date,Narration,Cheque No,Debit,Credit\n2026-10-01,Cash deposit,,,500\n");

    expect($read['errors'])->toBe([])->and($read['lines'][0]['deposit'])->toBe('500.00');
});

it('names every problem line and reads nothing wrongly', function (): void {
    $read = (new StatementCsv)->parse("date,description,withdrawal,deposit\n31/02/2026,Bad date,,10\n2026-10-02,No amount,,\n2026-10-03,Both,5,5\n2026-10-04,Text,abc,\n2026-10-05,Fine,,1\n");

    expect($read['errors'])->toHaveCount(4)->and($read['errors'][0])->toContain('Line 2')->and($read['lines'])->toHaveCount(1);
});

it('refuses a file without the columns it needs, or without lines', function (): void {
    expect((new StatementCsv)->parse('')['errors'])->not->toBe([])
        ->and((new StatementCsv)->parse("description,balance\nx,1\n")['errors'][0])->toContain('date column')
        ->and((new StatementCsv)->parse("date,description\n2026-10-01,x\n")['errors'][0])->toContain('withdrawal')
        ->and((new StatementCsv)->parse("date,withdrawal,deposit\n")['errors'])->not->toBe([]);
});
