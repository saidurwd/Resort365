<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Modules\Billing\Enums\FolioLineType;

/**
 * Folio arithmetic without database access. Amounts are decimal strings with two places.
 */
class FolioMath
{
    /**
     * What is owed: charges, adjustments and refunds less payments, ignoring voided lines.
     *
     * @param  iterable<array{type: FolioLineType, total: string, voided: bool}>  $lines
     */
    public function balance(iterable $lines): string
    {
        $balance = BigDecimal::zero();

        foreach ($lines as $line) {
            if (! $line['voided']) {
                $balance = $balance->plus(BigDecimal::of($line['total'])->multipliedBy($line['type']->sign()));
            }
        }

        return (string) $balance->toScale(2);
    }

    /**
     * Whether posting $amount keeps the balance within a credit limit ("0" or null = no limit).
     */
    public function withinCreditLimit(string $balance, string $amount, ?string $limit): bool
    {
        if ($limit === null || BigDecimal::of($limit)->isLessThanOrEqualTo(0)) {
            return true;
        }

        return BigDecimal::of($balance)->plus($amount)->isLessThanOrEqualTo($limit);
    }
}
