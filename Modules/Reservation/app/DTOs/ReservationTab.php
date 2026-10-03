<?php

namespace Modules\Reservation\DTOs;

use App\Support\DTOs\Data;
use Closure;
use Illuminate\Contracts\View\View;

/**
 * A tab another module adds to the reservation page (e.g. Billing's Payments). render receives
 * the reservation id and returns the tab's content. The tab shows only to users with the
 * permission, and only while the module is enabled for the tenant.
 */
final readonly class ReservationTab extends Data
{
    /**
     * @param  Closure(int): (View|string)  $render
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $icon,
        public string $permission,
        public string $module,
        public Closure $render,
        public int $order = 100,
    ) {}
}
