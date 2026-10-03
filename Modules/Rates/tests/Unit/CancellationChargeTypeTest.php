<?php

use Modules\Rates\Enums\CancellationChargeType;
use Tests\TestCase;

uses(TestCase::class); // describe() translates

it('describes charges in words without mangling whole numbers', function (): void {
    expect(CancellationChargeType::PercentOfDeposit->describe('50.00'))->toBe('50% of the deposit')
        ->and(CancellationChargeType::PercentOfTotal->describe('100'))->toBe('100% of the stay total')
        ->and(CancellationChargeType::Nights->describe('1.00'))->toBe('1 night')
        ->and(CancellationChargeType::Fixed->describe('2500'))->toBe('2,500.00');
});
