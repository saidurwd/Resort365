<?php

use Carbon\CarbonImmutable;
use Modules\Rates\Support\DaysOfWeek;
use Tests\TestCase;

uses(TestCase::class); // label() translates

it('builds and reads masks in ISO order', function (): void {
    expect(DaysOfWeek::mask([1]))->toBe(1)
        ->and(DaysOfWeek::mask([5, 6]))->toBe(48)
        ->and(DaysOfWeek::mask(range(1, 7)))->toBe(DaysOfWeek::EVERY_DAY)
        ->and(DaysOfWeek::days(48))->toBe([5, 6])
        ->and(DaysOfWeek::count(48))->toBe(2);
});

it('checks a date against a mask', function (): void {
    expect(DaysOfWeek::includes(48, CarbonImmutable::parse('2026-07-10')))->toBeTrue() // Friday
        ->and(DaysOfWeek::includes(48, CarbonImmutable::parse('2026-07-12')))->toBeFalse(); // Sunday
});

it('parses a weekend setting and labels masks', function (): void {
    expect(DaysOfWeek::parse('5, 6'))->toBe([5, 6])
        ->and(DaysOfWeek::parse('6,7,9,x'))->toBe([6, 7])
        ->and(DaysOfWeek::label(48))->toBe('Fri, Sat')
        ->and(DaysOfWeek::label(127))->toBe('Every day');
});

it('rejects a day outside 1–7', function (): void {
    DaysOfWeek::mask([0]);
})->throws(InvalidArgumentException::class);
