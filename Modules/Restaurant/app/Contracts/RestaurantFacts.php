<?php

namespace Modules\Restaurant\Contracts;

use Modules\Restaurant\DTOs\BillFact;
use Modules\Restaurant\DTOs\SessionFact;

/**
 * What Accounting reads from the restaurant to post the general ledger (ARCHITECTURE §7.1, Step 4.3).
 * Facts only; Restaurant never calls Accounting.
 */
interface RestaurantFacts
{
    /**
     * A bill that was settled (also one voided afterwards, as it was when settled), or null.
     */
    public function bill(int $billId): ?BillFact;

    /**
     * A closed session.
     */
    public function session(int $sessionId): ?SessionFact;

    /**
     * The tenant's outlets, for the account mapping screen.
     *
     * @return list<array{id: int, name: string, property: string}>
     */
    public function outlets(): array;

    /**
     * Ids of settled (or voided after settling) bills and closed sessions, for back-posting.
     *
     * @return array{bills: list<int>, sessions: list<int>}
     */
    public function history(): array;
}
