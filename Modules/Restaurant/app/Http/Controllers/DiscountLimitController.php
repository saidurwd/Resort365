<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\IAM\Contracts\RoleDirectory;
use Modules\Restaurant\Actions\SaveDiscountLimits;
use Modules\Restaurant\Http\Requests\DiscountLimitsRequest;
use Modules\Restaurant\Services\DiscountLimits;

/**
 * Restaurant → Discount limits: the largest discount % each role may give on the POS without a manager.
 */
class DiscountLimitController extends Controller
{
    public function index(RoleDirectory $roles, DiscountLimits $limits): View
    {
        return view('restaurant::discount-limits.index', ['roles' => $roles->all(), 'limits' => $limits->all()]);
    }

    public function update(DiscountLimitsRequest $request, SaveDiscountLimits $save): RedirectResponse
    {
        $save->handle($request->limits());

        return to_route('restaurant.discount-limits.index')->with('success', __('Discount limits saved.'));
    }
}
