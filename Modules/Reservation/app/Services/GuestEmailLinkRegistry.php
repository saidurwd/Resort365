<?php

namespace Modules\Reservation\Services;

use Closure;
use Modules\Reservation\Contracts\GuestEmailLinks;

class GuestEmailLinkRegistry implements GuestEmailLinks
{
    /** @var list<Closure(int): (array{label: string, url: string}|null)> */
    private array $providers = [];

    public function register(Closure $provider): void
    {
        $this->providers[] = $provider;
    }

    public function for(int $reservationId): array
    {
        return array_values(array_filter(array_map(fn (Closure $provider): ?array => $provider($reservationId), $this->providers)));
    }
}
