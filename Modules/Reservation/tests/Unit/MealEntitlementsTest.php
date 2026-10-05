<?php

/*
| Which meals a meal plan gives on a date of a stay (Step 3.7): breakfast the morning after a night
| stayed, lunch and dinner on a night being stayed. A stay 10 → 13 Nov.
*/

use Modules\Reservation\Services\MealEntitlements;

it('gives CP breakfast the mornings after the nights stayed', function (): void {
    $meals = new MealEntitlements;

    expect($meals->periods('CP', '2026-11-10', '2026-11-13', '2026-11-10'))->toBe([])
        ->and($meals->periods('CP', '2026-11-10', '2026-11-13', '2026-11-11'))->toBe(['breakfast'])
        ->and($meals->periods('CP', '2026-11-10', '2026-11-13', '2026-11-13'))->toBe(['breakfast'])
        ->and($meals->periods('CP', '2026-11-10', '2026-11-13', '2026-11-14'))->toBe([]);
});

it('gives MAP dinner on the nights stayed and AP lunch too', function (): void {
    $meals = new MealEntitlements;

    expect($meals->periods('MAP', '2026-11-10', '2026-11-13', '2026-11-10'))->toBe(['dinner'])
        ->and($meals->periods('MAP', '2026-11-10', '2026-11-13', '2026-11-12'))->toBe(['breakfast', 'dinner'])
        ->and($meals->periods('MAP', '2026-11-10', '2026-11-13', '2026-11-13'))->toBe(['breakfast'])
        ->and($meals->periods('AP', '2026-11-10', '2026-11-13', '2026-11-11'))->toBe(['breakfast', 'lunch', 'dinner'])
        ->and($meals->periods('AP', '2026-11-10', '2026-11-13', '2026-11-10'))->toBe(['lunch', 'dinner']);
});

it('gives nothing on a room-only plan', function (): void {
    expect((new MealEntitlements)->periods('EP', '2026-11-10', '2026-11-13', '2026-11-11'))->toBe([]);
});
