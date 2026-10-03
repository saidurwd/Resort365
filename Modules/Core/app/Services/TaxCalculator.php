<?php

namespace Modules\Core\Services;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DomainException;
use Modules\Core\DTOs\TaxBreakdown;
use Modules\Core\DTOs\TaxLine;
use Modules\Core\DTOs\TaxRule;
use Modules\Core\Enums\TaxType;

/**
 * Applies taxes to an amount (ARCHITECTURE §5.1). No database access; rules come in calculation order.
 *
 * - A percent tax is calculated on the net amount, or for a compound tax on the net amount plus
 *   the taxes before it. A fixed tax is its rate × quantity (e.g. a levy per room per night).
 * - Exclusive: the amount is the net; taxes are added on top.
 * - Inclusive: the amount is the gross; the net is backed out of it.
 * - Each tax is rounded to 2 places (half up). For inclusive amounts net = gross − taxes,
 *   so the parts always add up to the price.
 *
 * Example: 1000.00 with 10% service charge, then 15% VAT (compound): 100.00 + 165.00 = 1265.00.
 */
class TaxCalculator
{
    private const int SCALE = 2;

    private const int WORKING_SCALE = 12;

    /**
     * @param  list<TaxRule>  $rules
     */
    public function calculate(string $amount, array $rules, bool $inclusive = false, int $quantity = 1): TaxBreakdown
    {
        $amount = BigDecimal::of($amount);

        if (! $inclusive) {
            $lines = $this->apply($amount, $rules, $quantity);
            $net = $amount->toScale(self::SCALE, RoundingMode::HalfUp);

            return $this->breakdown($net, $lines);
        }

        // gross = k × net + c, because every tax is linear in the net amount.
        $k = $this->total(BigDecimal::one(), $rules, 0);
        $c = $this->total(BigDecimal::zero(), $rules, $quantity);
        $net = $amount->minus($c)->dividedBy($k, self::WORKING_SCALE, RoundingMode::HalfUp);

        if ($net->isNegative() && ! $amount->isNegative()) {
            throw new DomainException('The price is lower than its fixed taxes.');
        }

        $lines = $this->apply($net, $rules, $quantity);
        $taxTotal = array_reduce($lines, fn (BigDecimal $sum, TaxLine $line): BigDecimal => $sum->plus($line->amount), BigDecimal::zero());

        return $this->breakdown($amount->toScale(self::SCALE, RoundingMode::HalfUp)->minus($taxTotal), $lines);
    }

    /**
     * Rounded tax lines for a net amount.
     *
     * @param  list<TaxRule>  $rules
     * @return list<TaxLine>
     */
    private function apply(BigDecimal $net, array $rules, int $quantity): array
    {
        $lines = [];
        $previous = BigDecimal::zero();

        foreach ($rules as $rule) {
            $base = $rule->compound ? $net->plus($previous) : $net;
            $tax = $this->tax($rule, $base, $quantity)->toScale(self::SCALE, RoundingMode::HalfUp);
            $previous = $previous->plus($tax);
            $lines[] = new TaxLine($rule->code, $rule->name, $rule->type, $rule->rate, (string) $tax);
        }

        return $lines;
    }

    /**
     * Net plus unrounded taxes (used to back out an inclusive price).
     *
     * @param  list<TaxRule>  $rules
     */
    private function total(BigDecimal $net, array $rules, int $quantity): BigDecimal
    {
        $previous = BigDecimal::zero();

        foreach ($rules as $rule) {
            $base = $rule->compound ? $net->plus($previous) : $net;
            $previous = $previous->plus($this->tax($rule, $base, $quantity));
        }

        return $net->plus($previous);
    }

    private function tax(TaxRule $rule, BigDecimal $base, int $quantity): BigDecimal
    {
        return match ($rule->type) {
            TaxType::Percent => $base->multipliedBy($rule->rate)->dividedBy(100, self::WORKING_SCALE, RoundingMode::HalfUp),
            TaxType::Fixed => BigDecimal::of($rule->rate)->multipliedBy($quantity),
        };
    }

    /**
     * @param  list<TaxLine>  $lines
     */
    private function breakdown(BigDecimal $net, array $lines): TaxBreakdown
    {
        $taxTotal = array_reduce($lines, fn (BigDecimal $sum, TaxLine $line): BigDecimal => $sum->plus($line->amount), BigDecimal::zero())
            ->toScale(self::SCALE);

        return new TaxBreakdown((string) $net->toScale(self::SCALE), $lines, (string) $taxTotal, (string) $net->plus($taxTotal)->toScale(self::SCALE));
    }
}
