<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Support\Cash\CashCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Http\Controllers\Concerns\CurrentProperty;
use Modules\Restaurant\Http\Controllers\Pos\PosSessionController;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\PosSessionsTable;
use Modules\Restaurant\Services\SessionCash;

/**
 * Restaurant → POS sessions (back office): every session of the current property with its variance,
 * and its X or Z report. Authorized by the restaurant.session.view route middleware.
 */
class PosSessionsController extends Controller
{
    use CurrentProperty;

    public function index(): View
    {
        $this->propertyId();

        return view('restaurant::sessions.index', ['columns' => PosSessionsTable::columns()]);
    }

    public function data(PosSessionsTable $table): JsonResponse
    {
        return $table->toJson($this->propertyId());
    }

    public function report(PosSession $session, SessionCash $cash, CashCount $count, UserDirectory $users, PropertyDirectory $properties): View
    {
        return PosSessionController::reportView($session, $cash, $count, $users, $properties);
    }
}
