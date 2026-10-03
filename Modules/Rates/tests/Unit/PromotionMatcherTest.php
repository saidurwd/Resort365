<?php

use Modules\Rates\DTOs\PromotionTerms;
use Modules\Rates\DTOs\StayRequest;
use Modules\Rates\Enums\DiscountType;
use Modules\Rates\Services\PromotionMatcher;

/**
 * @param  array<string, string>  $nightly
 */
function stay(array $nightly, string $bookedOn = '2026-05-01', ?string $code = null, int $plan = 1, string $unit = 'room_type:1'): StayRequest
{
    return new StayRequest($plan, $unit, $nightly, $bookedOn, $code);
}

/**
 * @return array<string, string>
 */
function nights(string $from, int $count, string $amount = '6000.00'): array
{
    $nights = [];
    $date = new DateTimeImmutable($from);

    for ($i = 0; $i < $count; $i++) {
        $nights[$date->modify("+{$i} days")->format('Y-m-d')] = $amount;
    }

    return $nights;
}

function longStay(): PromotionTerms
{
    return new PromotionTerms(1, null, 'Long stay', DiscountType::Percent, '10', minNights: 7);
}

function monsoon(): PromotionTerms
{
    return new PromotionTerms(2, 'MONSOON20', 'Monsoon offer', DiscountType::Percent, '20', stayFrom: '2026-06-01', stayTo: '2026-08-31');
}

function earlyBird(): PromotionTerms
{
    return new PromotionTerms(3, 'EARLYBIRD', 'Early bird', DiscountType::FixedPerNight, '1000', minAdvanceDays: 30);
}

it('applies a long-stay discount automatically from 7 nights', function (): void {
    $matcher = new PromotionMatcher;

    expect($matcher->discount(longStay(), stay(nights('2026-07-01', 7)))?->amount)->toBe('4200.00')
        ->and($matcher->discount(longStay(), stay(nights('2026-07-01', 6))))->toBeNull();
});

it('needs the promo code, in any letter case, for a code promotion', function (): void {
    $matcher = new PromotionMatcher;

    expect($matcher->discount(monsoon(), stay(nights('2026-07-01', 2))))->toBeNull()
        ->and($matcher->discount(monsoon(), stay(nights('2026-07-01', 2), code: 'WRONG')))->toBeNull()
        ->and($matcher->discount(monsoon(), stay(nights('2026-07-01', 2), code: 'monsoon20'))?->amount)->toBe('2400.00');
});

it('discounts only the nights inside the stay window', function (): void {
    // 30 and 31 Aug are inside, 1 Sep is not.
    $discount = new PromotionMatcher()->discount(monsoon(), stay(nights('2026-08-30', 3), code: 'MONSOON20'));

    expect($discount?->amount)->toBe('2400.00')->and($discount?->nights)->toBe(2);
});

it('checks how far ahead the stay was booked', function (): void {
    $matcher = new PromotionMatcher;

    expect($matcher->discount(earlyBird(), stay(nights('2026-07-01', 3), '2026-06-01', 'EARLYBIRD'))?->amount)->toBe('3000.00')
        ->and($matcher->discount(earlyBird(), stay(nights('2026-07-01', 3), '2026-06-02', 'EARLYBIRD')))->toBeNull();
});

it('checks the booking window, plan, type, nights and usage limit', function (): void {
    $matcher = new PromotionMatcher;
    $base = ['id' => 9, 'code' => null, 'name' => 'Test', 'discountType' => DiscountType::Percent, 'discountValue' => '10'];
    $promo = fn (array $extra): PromotionTerms => PromotionTerms::from([...$base, ...$extra]);
    $twoNights = nights('2026-07-01', 2);

    expect($matcher->discount($promo(['bookFrom' => '2026-05-02']), stay($twoNights)))->toBeNull()
        ->and($matcher->discount($promo(['bookTo' => '2026-04-30']), stay($twoNights)))->toBeNull()
        ->and($matcher->discount($promo(['ratePlanIds' => [2]]), stay($twoNights)))->toBeNull()
        ->and($matcher->discount($promo(['ratePlanIds' => [1, 2]]), stay($twoNights)))->not->toBeNull()
        ->and($matcher->discount($promo(['unitKeys' => ['cottage_type:3']]), stay($twoNights)))->toBeNull()
        ->and($matcher->discount($promo(['maxNights' => 1]), stay($twoNights)))->toBeNull()
        ->and($matcher->discount($promo(['usageLimit' => 5, 'timesUsed' => 5]), stay($twoNights)))->toBeNull()
        ->and($matcher->discount($promo(['isActive' => false]), stay($twoNights)))->toBeNull();
});

it('caps fixed discounts at the price', function (): void {
    $matcher = new PromotionMatcher;
    $perNight = new PromotionTerms(4, null, 'Big', DiscountType::FixedPerNight, '9000');
    $perStay = new PromotionTerms(5, null, 'Gift', DiscountType::FixedPerStay, '50000', stayFrom: '2026-07-01');

    expect($matcher->discount($perNight, stay(nights('2026-07-01', 2)))?->amount)->toBe('12000.00')
        ->and($matcher->discount($perStay, stay(nights('2026-07-01', 2)))?->amount)->toBe('12000.00')
        ->and($matcher->discount($perStay, stay(nights('2026-06-30', 2))))->toBeNull();
});

it('picks the best single promotion', function (): void {
    $matcher = new PromotionMatcher;
    $stay = stay(nights('2026-07-01', 7), '2026-05-01', 'MONSOON20');

    expect($matcher->best([longStay(), monsoon(), earlyBird()], $stay)?->name)->toBe('Monsoon offer') // 20% of 42,000 = 8,400
        ->and($matcher->best([longStay(), earlyBird()], stay(nights('2026-07-01', 7), '2026-05-01', 'EARLYBIRD'))?->amount)->toBe('7000.00')
        ->and($matcher->best([monsoon()], stay(nights('2026-07-01', 2))))->toBeNull();
});
