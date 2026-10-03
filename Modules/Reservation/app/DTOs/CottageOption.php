<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Property\DTOs\CottageSummary;

/**
 * A whole cottage that is free for the stay, with its price (null = no rate) and any
 * restrictions that stop it being sold.
 */
final readonly class CottageOption extends Data
{
    /**
     * @param  list<string>  $roomNumbers
     * @param  list<RestrictionViolation>  $violations
     */
    public function __construct(
        public CottageSummary $cottage,
        public string $typeName,
        public array $roomNumbers,
        public bool $fits,
        public ?PriceQuote $quote,
        public array $violations,
    ) {}

    public function isBookable(): bool
    {
        return $this->quote instanceof PriceQuote && $this->violations === [];
    }
}
