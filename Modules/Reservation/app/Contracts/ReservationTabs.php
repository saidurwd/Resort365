<?php

namespace Modules\Reservation\Contracts;

use Modules\Reservation\DTOs\ReservationTab;

/**
 * Tabs that downstream modules add to the reservation page (Billing: Payments; later folios),
 * since Reservation may not call them itself (ARCHITECTURE §4.3). Register them in your provider.
 */
interface ReservationTabs
{
    public function add(ReservationTab $tab): void;

    /**
     * Every registered tab, in order.
     *
     * @return list<ReservationTab>
     */
    public function all(): array;
}
