<?php

namespace Modules\Reservation\Contracts;

use Closure;

/**
 * Links that downstream modules add to a booking's guest emails (Step 8.2b; the Website module: "Manage your booking"), since
 * Reservation may not call them itself. The emails print them in the `{links}` placeholder. Register a provider in your provider.
 */
interface GuestEmailLinks
{
    /**
     * @param  Closure(int $reservationId): (array{label: string, url: string}|null)  $provider
     */
    public function register(Closure $provider): void;

    /**
     * The links for a booking, from every provider that has one.
     *
     * @return list<array{label: string, url: string}>
     */
    public function for(int $reservationId): array;
}
