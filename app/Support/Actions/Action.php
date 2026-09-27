<?php

namespace App\Support\Actions;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Base class for a single use case (e.g. CreateReservation, PostJournalEntry).
 *
 * Conventions (docs/ARCHITECTURE.md §4.4, §12):
 * - One public `handle()` method that takes typed arguments or a DTO and returns a model or DTO.
 * - Dependencies are constructor-injected; resolve the action with `make()` or inject it.
 * - An action that writes more than one row wraps the work in `transaction()`.
 * - Domain events implement `ShouldDispatchAfterCommit`, so they fire only if the transaction commits.
 */
abstract class Action
{
    /**
     * Resolve the action from the container with its dependencies injected.
     */
    public static function make(): static
    {
        return app(static::class);
    }

    /**
     * Run the callback in a database transaction, retrying on deadlock.
     *
     * @template TReturn
     *
     * @param  Closure(): TReturn  $callback
     * @return TReturn
     */
    protected function transaction(Closure $callback, int $attempts = 1): mixed
    {
        return DB::transaction($callback, $attempts);
    }
}
