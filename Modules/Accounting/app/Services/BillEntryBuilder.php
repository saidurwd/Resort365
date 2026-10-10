<?php

namespace Modules\Accounting\Services;

use Brick\Math\BigDecimal;
use Modules\Accounting\Exceptions\AccountingRuleViolated;
use Modules\Restaurant\DTOs\BillFact;

/**
 * Works out what a settled restaurant bill posts (ARCHITECTURE §7.1): what each tender received, the
 * food and beverage revenue, service charge, taxes and tips. Meal-plan and complimentary tenders post
 * nothing. A rounding difference between the bill's lines and its totals (a few cents) goes to the larger
 * revenue class, so the entry balances; anything bigger means the bill does not add up. Pure.
 */
class BillEntryBuilder
{
    private const array NO_TENDER = ['package', 'complimentary'];

    /**
     * @return array{tenders: array<string, string>, revenue: array{food: string, beverage: string}, service: string, taxes: array<string, string>, tips: string}|null null when nothing is posted
     *
     * @throws AccountingRuleViolated
     */
    public function build(BillFact $bill): ?array
    {
        if ($bill->complimentary) {
            return null;
        }

        $tenders = array_filter($bill->payments, fn (string $amount, string $method): bool => ! in_array($method, self::NO_TENDER, true) && BigDecimal::of($amount)->isPositive(), ARRAY_FILTER_USE_BOTH);

        if ($tenders === []) {
            return null;
        }

        $received = array_reduce($tenders, fn (BigDecimal $sum, string $amount): BigDecimal => $sum->plus($amount), BigDecimal::zero());
        $food = BigDecimal::of($bill->revenue['food']);
        $beverage = BigDecimal::of($bill->revenue['beverage']);
        $credits = $food->plus($beverage)->plus($bill->serviceCharge)->plus($bill->tips);

        foreach ($bill->taxes as $amount) {
            $credits = $credits->plus($amount);
        }

        $difference = $received->minus($credits);

        if ($difference->abs()->isGreaterThan('1.00')) {
            throw new AccountingRuleViolated(__('Bill :no does not add up: the payments are :paid and its lines come to :lines.', ['no' => $bill->billNo, 'paid' => (string) $received->toScale(2), 'lines' => (string) $credits->toScale(2)]));
        }

        if ($food->isGreaterThanOrEqualTo($beverage)) {
            $food = $food->plus($difference);
        } else {
            $beverage = $beverage->plus($difference);
        }

        return [
            'tenders' => array_map(fn (string $amount): string => (string) BigDecimal::of($amount)->toScale(2), $tenders),
            'revenue' => ['food' => (string) $food->toScale(2), 'beverage' => (string) $beverage->toScale(2)],
            'service' => (string) BigDecimal::of($bill->serviceCharge)->toScale(2),
            'taxes' => $bill->taxes,
            'tips' => (string) BigDecimal::of($bill->tips)->toScale(2),
        ];
    }
}
