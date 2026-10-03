<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\DTOs\DepositPolicySummary;
use Modules\Rates\DTOs\DepositQuote;
use Modules\Rates\DTOs\PromotionDiscount;

/**
 * The price of a whole booking (BookingQuoter): its items, totals (decimal strings) and deposit.
 * depositWithinLimits is false when the chosen percent needs reservation.deposit.override.
 */
final readonly class BookingQuote extends Data
{
    /**
     * @param  list<QuotedItem>  $items
     */
    public function __construct(
        public array $items,
        public string $subtotal,
        public string $discount,
        public string $tax,
        public string $total,
        public DepositQuote $deposit,
        public ?DepositPolicySummary $depositPolicy,
        public bool $depositWithinLimits,
        public ?PromotionDiscount $promotion,
        public string $currency,
    ) {}
}
