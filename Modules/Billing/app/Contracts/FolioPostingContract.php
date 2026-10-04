<?php

namespace Modules\Billing\Contracts;

use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\DTOs\FolioPosting;
use Modules\Billing\Exceptions\ChargeRejected;

/**
 * How other modules (Restaurant, later spa or laundry) post a charge to an in-house guest's folio
 * (ARCHITECTURE §5.9). The charge is refused (ChargeRejected, with the reason) when the guest is
 * not checked in, the folio is not open, or the folio's credit limit would be exceeded.
 */
interface FolioPostingContract
{
    /**
     * @throws ChargeRejected
     */
    public function postCharge(FolioCharge $charge): FolioPosting;
}
