<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;

/**
 * The meals a booking's rate plans include on a date (ARCHITECTURE §5.10.9), from its checked-in rooms:
 * breakfast the morning after a night stayed; lunch and dinner on a night being stayed. Covers per
 * meal period ("breakfast", "lunch", "dinner"); a period not included is missing.
 */
final readonly class MealEntitlement extends Data
{
    /**
     * @param  array<string, array{adults: int, children: int}>  $periods
     * @param  list<string>  $mealPlans  e.g. ["CP"]
     */
    public function __construct(
        public int $reservationId,
        public string $date,
        public array $periods,
        public array $mealPlans,
    ) {}

    public function covers(string $period): int
    {
        return ($this->periods[$period]['adults'] ?? 0) + ($this->periods[$period]['children'] ?? 0);
    }
}
