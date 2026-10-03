<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Rates\DTOs\PromotionDiscount;

/**
 * The price of one item (a room of a type, or a whole cottage) for a stay in a rate plan.
 * subtotal = base + extras of all nights; total = subtotal − discount + tax (exclusive) or the
 * prices as given (inclusive). mealComponent is the part that pays for included meals.
 */
final readonly class PriceQuote extends Data
{
    /**
     * @param  list<PricedNight>  $nights
     */
    public function __construct(
        public int $ratePlanId,
        public string $unitKey,
        public Occupancy $occupancy,
        public array $nights,
        public string $subtotal,
        public string $discount,
        public string $net,
        public string $tax,
        public string $total,
        public string $mealComponent,
        public ?PromotionDiscount $promotion = null,
        public bool $fitsParty = true,
    ) {}

    public function averagePerNight(): string
    {
        return $this->nights === [] ? '0.00' : bcdiv($this->total, (string) count($this->nights), 2);
    }
}
