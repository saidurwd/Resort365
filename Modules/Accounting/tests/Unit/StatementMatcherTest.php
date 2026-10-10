<?php

use Modules\Accounting\Services\StatementMatcher;

/**
 * @param  list<array{id: int, date: string, amount: string, reference: string|null}>  $statement
 * @param  list<array{id: int, date: string, amount: string, text: string}>  $ledger
 * @return list<array{statement: int, ledger: int}>
 */
function suggest(array $statement, array $ledger, int $window = 5): array
{
    return (new StatementMatcher)->suggest($statement, $ledger, $window);
}

it('pairs lines of the same amount and a near date', function (): void {
    $pairs = suggest(
        [['id' => 1, 'date' => '2026-10-10', 'amount' => '500.00', 'reference' => null], ['id' => 2, 'date' => '2026-10-11', 'amount' => '-200.00', 'reference' => null]],
        [['id' => 10, 'date' => '2026-10-09', 'amount' => '500.00', 'text' => 'a'], ['id' => 11, 'date' => '2026-10-11', 'amount' => '-200.00', 'text' => 'b']],
    );

    expect($pairs)->toBe([['statement' => 2, 'ledger' => 11], ['statement' => 1, 'ledger' => 10]]);
});

it('keeps a deposit and a withdrawal of the same size apart', function (): void {
    expect(suggest([['id' => 1, 'date' => '2026-10-10', 'amount' => '500.00', 'reference' => null]], [['id' => 10, 'date' => '2026-10-10', 'amount' => '-500.00', 'text' => '']]))->toBe([]);
});

it('leaves out a date beyond the window', function (): void {
    $line = [['id' => 1, 'date' => '2026-10-20', 'amount' => '500.00', 'reference' => null]];
    $book = [['id' => 10, 'date' => '2026-10-10', 'amount' => '500.00', 'text' => '']];

    expect(suggest($line, $book, 5))->toBe([])->and(suggest($line, $book, 10))->toBe([['statement' => 1, 'ledger' => 10]]);
});

it('prefers the reference and then the nearer date, using each line once', function (): void {
    $pairs = suggest(
        [['id' => 1, 'date' => '2026-10-10', 'amount' => '100.00', 'reference' => 'CHQ-9'], ['id' => 2, 'date' => '2026-10-10', 'amount' => '100.00', 'reference' => null]],
        [['id' => 10, 'date' => '2026-10-10', 'amount' => '100.00', 'text' => 'Paid by cheque'], ['id' => 11, 'date' => '2026-10-12', 'amount' => '100.00', 'text' => 'cheque chq-9']],
    );

    expect($pairs)->toBe([['statement' => 1, 'ledger' => 11], ['statement' => 2, 'ledger' => 10]]);
});
