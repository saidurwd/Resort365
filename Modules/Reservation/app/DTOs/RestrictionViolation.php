<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Modules\Reservation\Enums\StayRestriction;

/**
 * Why a stay cannot be sold: the restriction, the date it applies on and, for minimum and
 * maximum stay, the number of nights required.
 */
final readonly class RestrictionViolation extends Data
{
    public function __construct(
        public StayRestriction $restriction,
        public ?string $date = null,
        public ?int $nights = null,
    ) {}

    public function message(): string
    {
        return match ($this->restriction) {
            StayRestriction::MinStay => trans_choice('Minimum stay :count night|Minimum stay :count nights', (int) $this->nights),
            StayRestriction::MaxStay => trans_choice('Maximum stay :count night|Maximum stay :count nights', (int) $this->nights),
            StayRestriction::StopSell => __('Stop sell on :date', ['date' => (string) $this->date]),
            default => $this->restriction->label(),
        };
    }
}
