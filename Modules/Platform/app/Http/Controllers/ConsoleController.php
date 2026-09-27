<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

/**
 * Platform console home. TODO(step-7.3): tenants, plans, usage, suspend/reactivate, impersonation.
 */
class ConsoleController extends Controller
{
    public function __invoke(): View
    {
        return view('platform::console', ['admin' => auth('platform')->user()]);
    }
}
