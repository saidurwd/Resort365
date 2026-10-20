<?php

namespace Modules\Accounting\Contracts;

use Modules\Accounting\DTOs\CashBalance;

/**
 * The cash and bank balances in the books (ARCHITECTURE §5.17, Step 7.1), for the management dashboard.
 */
interface CashPosition
{
    /**
     * Every active cash and bank account with its ledger balance on the date.
     *
     * @return list<CashBalance>
     */
    public function balances(string $date): array;
}
