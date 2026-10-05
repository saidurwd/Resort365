<?php

/*
| KotTransitions: what a tap on the kitchen display does to a ticket (Step 3.5).
*/

use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;
use Modules\Restaurant\Services\KotTransitions;

it('moves a new ticket through preparing and ready to done, and recalls it', function (): void {
    $next = fn (KotStatus $status, string $action): ?KotStatus => (new KotTransitions)->next(KotType::New, $status, $action);

    expect($next(KotStatus::New, 'start'))->toBe(KotStatus::Preparing)
        ->and($next(KotStatus::New, 'ready'))->toBe(KotStatus::Ready)
        ->and($next(KotStatus::Preparing, 'ready'))->toBe(KotStatus::Ready)
        ->and($next(KotStatus::Ready, 'bump'))->toBe(KotStatus::Done)
        ->and($next(KotStatus::Done, 'recall'))->toBe(KotStatus::Ready);
});

it('refuses taps that do not fit the ticket', function (): void {
    $next = fn (KotStatus $status, string $action): ?KotStatus => (new KotTransitions)->next(KotType::New, $status, $action);

    expect($next(KotStatus::New, 'bump'))->toBeNull()
        ->and($next(KotStatus::Preparing, 'start'))->toBeNull()
        ->and($next(KotStatus::Ready, 'ready'))->toBeNull()
        ->and($next(KotStatus::Done, 'bump'))->toBeNull()
        ->and($next(KotStatus::Ready, 'recall'))->toBeNull()
        ->and($next(KotStatus::New, 'explode'))->toBeNull();
});

it('only acknowledges a void ticket', function (): void {
    $next = fn (KotStatus $status, string $action): ?KotStatus => (new KotTransitions)->next(KotType::Void, $status, $action);

    expect($next(KotStatus::New, 'bump'))->toBe(KotStatus::Done)
        ->and($next(KotStatus::Done, 'recall'))->toBe(KotStatus::New)
        ->and($next(KotStatus::New, 'start'))->toBeNull()
        ->and($next(KotStatus::New, 'ready'))->toBeNull();
});
