<?php

namespace Modules\Reservation\Services;

use Modules\Reservation\Contracts\ReservationTabs;
use Modules\Reservation\DTOs\ReservationTab;

/**
 * The reservation page's extra tabs (a singleton filled by module providers).
 */
class ReservationTabRegistry implements ReservationTabs
{
    /**
     * @var array<string, ReservationTab>
     */
    private array $tabs = [];

    public function add(ReservationTab $tab): void
    {
        $this->tabs[$tab->key] = $tab;
    }

    public function all(): array
    {
        $tabs = array_values($this->tabs);
        usort($tabs, fn (ReservationTab $a, ReservationTab $b): int => $a->order <=> $b->order);

        return $tabs;
    }
}
