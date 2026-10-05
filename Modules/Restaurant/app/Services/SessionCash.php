<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Models\PosSession;

/**
 * The cash a POS session took and paid back, and its bills still open (ARCHITECTURE §5.10.11).
 * TODO(step-3.6): sum the session's cash payments and refunds, and count its open bills, once POS
 * bills and payments exist; until then a session holds just its float.
 */
class SessionCash
{
    /**
     * @return array{string, string} cash received, cash refunded
     */
    public function cash(PosSession $session): array
    {
        return ['0.00', '0.00'];
    }

    public function openBills(PosSession $session): int
    {
        return 0;
    }
}
