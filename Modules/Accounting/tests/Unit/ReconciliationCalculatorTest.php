<?php

use Modules\Accounting\Services\ReconciliationCalculator;

it('balances when the only differences are a cheque outstanding and a deposit in transit', function (): void {
    // Ledger 10,000: bank has 10,000 + 5,000 (cheque not cleared) - 2,000 (deposit not yet shown) = 13,000.
    $figures = (new ReconciliationCalculator)->calculate('13000.00', '10000.00', ['-5000.00', '2000.00'], []);

    expect($figures)->toBe([
        'statement_balance' => '13000.00', 'deposits_in_transit' => '2000.00', 'outstanding_payments' => '5000.00', 'adjusted_bank' => '10000.00', 'book_balance' => '10000.00',
        'bank_items_not_in_books' => '0.00', 'adjusted_book' => '10000.00', 'outstanding_net' => '-3000.00', 'difference' => '0.00',
    ]);
});

it('counts bank charges the books lack on the book side', function (): void {
    $figures = (new ReconciliationCalculator)->calculate('9750.00', '10000.00', [], ['-250.00']);

    expect($figures['adjusted_book'])->toBe('9750.00')->and($figures['difference'])->toBe('0.00');
});

it('shows what is left over when the figures disagree', function (): void {
    expect((new ReconciliationCalculator)->calculate('10100.00', '10000.00', [], [])['difference'])->toBe('100.00');
});
