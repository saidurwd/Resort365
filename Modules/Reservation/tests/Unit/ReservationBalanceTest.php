<?php

use Modules\Reservation\Enums\PaymentStatus;
use Modules\Reservation\Services\ReservationBalance;

/*
| A reservation's money rules (ARCHITECTURE §6.4, §6.5): deposit rounding, balance and payment status.
*/

it('rounds the deposit half up to the cent (rule 1)', function (string $total, string $percent, string $deposit): void {
    expect(new ReservationBalance()->deposit($total, $percent))->toBe($deposit);
})->with([
    'worked example §6.5: 30% of 64,515.00' => ['64515.00', '30', '19354.50'],
    'Step 1.6 booking: 30% of 136,620.00' => ['136620.00', '30.00', '40986.00'],
    'half a cent rounds up' => ['100.05', '50', '50.03'],
    'waived' => ['15180.00', '0', '0.00'],
    'whole stay' => ['15180.00', '100', '15180.00'],
]);

it('owes the total less what was paid, never below zero', function (string $owed, string $paid, string $balance): void {
    expect(new ReservationBalance()->balance($owed, $paid))->toBe($balance);
})->with([
    'nothing paid' => ['64515.00', '0.00', '64515.00'],
    'deposit paid' => ['64515.00', '19354.50', '45160.50'],
    'fully paid' => ['64515.00', '64515.00', '0.00'],
    'overpaid' => ['100.00', '150.00', '0.00'],
]);

it('derives the payment status from the total, the deposit and what was paid', function (string $paid, string $deposit, PaymentStatus $status): void {
    expect(new ReservationBalance()->status('64515.00', $paid, $deposit))->toBe($status);
})->with([
    'nothing paid' => ['0.00', '19354.50', PaymentStatus::Unpaid],
    'less than the deposit' => ['10000.00', '19354.50', PaymentStatus::Unpaid],
    'exactly the deposit' => ['19354.50', '19354.50', PaymentStatus::DepositPaid],
    'more than the deposit' => ['30000.00', '19354.50', PaymentStatus::DepositPaid],
    'no deposit, something paid' => ['500.00', '0.00', PaymentStatus::DepositPaid],
    'the whole total' => ['64515.00', '19354.50', PaymentStatus::FullyPaid],
    'more than the total' => ['64515.01', '19354.50', PaymentStatus::Overpaid],
]);

it('treats a zero deposit as met', function (): void {
    $balance = new ReservationBalance;

    expect($balance->depositMet('0.00', '0.00'))->toBeTrue()
        ->and($balance->depositMet('19354.49', '19354.50'))->toBeFalse()
        ->and($balance->depositMet('19354.50', '19354.50'))->toBeTrue();
});
