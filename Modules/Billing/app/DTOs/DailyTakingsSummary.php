<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * One business date's takings at a property (night audit and flash report): folio charges and
 * adjustments posted that day by category (net, tax, total), the meal part of room nights posted
 * (package split), and the money taken and paid back that day by method. Amounts are decimal
 * strings.
 */
final readonly class DailyTakingsSummary extends Data
{
    /**
     * @param  array<string, array{label: string, net: string, tax: string, total: string}>  $charges  category => totals
     * @param  array<string, array{label: string, received: string, refunded: string}>  $payments  method => totals
     */
    public function __construct(
        public string $date,
        public array $charges,
        public string $chargesTotal,
        public string $taxTotal,
        public string $packageMeals,
        public array $payments,
        public string $receivedTotal,
        public string $refundedTotal,
        public string $securityDeposits,
        public int $openShifts,
    ) {}
}
