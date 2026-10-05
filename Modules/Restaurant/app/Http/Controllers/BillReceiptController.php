<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Services\BillDocuments;

/**
 * A restaurant receipt in the back office: reprinted from a guest's folio (front desk) or from the
 * session reports. Reprints are marked COPY.
 */
class BillReceiptController extends Controller
{
    public function show(PosBill $bill, BillDocuments $documents): View
    {
        abort_unless(Gate::any(['billing.folio.view', 'restaurant.session.view']), 403);
        abort_unless($bill->status !== BillStatus::Printed, 404);

        return $documents->receipt($bill);
    }
}
