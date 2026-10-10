<?php

use Modules\Accounting\Services\JournalInvariants;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  list<array{string, string}>  $lines
 * @return list<array{debit: string, credit: string}>
 */
function inv(array $lines): array
{
    return array_map(fn (array $l): array => ['debit' => $l[0], 'credit' => $l[1]], $lines);
}

it('accepts a balanced entry', function (): void {
    expect(app(JournalInvariants::class)->problems(inv([['100.00', '0.00'], ['0.00', '100.00']])))->toBe([]);
});

it('accepts a balanced entry of many lines', function (): void {
    expect(app(JournalInvariants::class)->problems(inv([['0.10', '0.00'], ['0.20', '0.00'], ['0.00', '0.30']])))->toBe([]);
});

it('refuses an unbalanced entry', function (): void {
    expect(app(JournalInvariants::class)->problems(inv([['100.00', '0.00'], ['0.00', '99.99']])))->not->toBe([]);
});

it('refuses fewer than two lines', function (): void {
    expect(app(JournalInvariants::class)->problems(inv([['100.00', '0.00']])))->toHaveCount(1);
});

it('refuses a line with both sides or neither', function (): void {
    expect(app(JournalInvariants::class)->problems(inv([['5.00', '5.00'], ['0.00', '0.00']])))->not->toBe([]);
});

it('refuses negative amounts', function (): void {
    expect(app(JournalInvariants::class)->problems(inv([['-5.00', '0.00'], ['0.00', '-5.00']])))->not->toBe([]);
});

it('totals the debits', function (): void {
    expect(app(JournalInvariants::class)->total(inv([['100.00', '0.00'], ['25.50', '0.00'], ['0.00', '125.50']])))->toBe('125.50');
});
