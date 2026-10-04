<?php

use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Services\FolioMath;

it('owes charges, adjustments and refunds less payments, ignoring voided lines', function (): void {
    expect(new FolioMath()->balance([
        ['type' => FolioLineType::Charge, 'total' => '1265.00', 'voided' => false],
        ['type' => FolioLineType::Charge, 'total' => '500.00', 'voided' => true],
        ['type' => FolioLineType::Adjustment, 'total' => '-100.00', 'voided' => false],
        ['type' => FolioLineType::Payment, 'total' => '1000.00', 'voided' => false],
        ['type' => FolioLineType::Refund, 'total' => '200.00', 'voided' => false],
    ]))->toBe('365.00')
        ->and(new FolioMath()->balance([]))->toBe('0.00');
});

it('checks the credit limit; zero or none means unlimited', function (string $balance, string $amount, ?string $limit, bool $ok): void {
    expect(new FolioMath()->withinCreditLimit($balance, $amount, $limit))->toBe($ok);
})->with([
    'under' => ['1000.00', '500.00', '2000.00', true],
    'exactly at' => ['1500.00', '500.00', '2000.00', true],
    'over' => ['1500.01', '500.00', '2000.00', false],
    'no limit (0)' => ['999999.00', '1.00', '0', true],
    'no limit (null)' => ['999999.00', '1.00', null, true],
    'credit balance' => ['-5000.00', '6000.00', '2000.00', true],
]);
