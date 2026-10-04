<?php

namespace Modules\Restaurant\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Restaurant\Actions\SyncOutletAccess;
use Modules\Restaurant\Http\Requests\UpdateOutletAccessRequest;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Services\OutletAccess;

/**
 * Restaurant → Outlet access: which staff work in which outlets of the current property. Users with
 * restaurant.outlet.access-all work in every outlet. Authorized by restaurant.access.manage.
 */
class OutletAccessController extends Controller
{
    public function index(UserDirectory $users, OutletAccess $access): View
    {
        $propertyId = $this->propertyId();
        $staff = array_values(array_filter($users->all(), fn (UserSummary $user): bool => $user->status !== 'inactive'));

        return view('restaurant::access.index', [
            'users' => $staff,
            'outlets' => Outlet::query()->where('property_id', $propertyId)->orderBy('sort_order')->orderBy('name')->get(['id', 'code', 'name']),
            'assigned' => $access->assignments($propertyId),
            'seesAll' => collect($staff)->mapWithKeys(fn (UserSummary $user): array => [$user->id => $users->userCan($user->id, OutletAccess::ACCESS_ALL)])->all(),
        ]);
    }

    public function update(UpdateOutletAccessRequest $request, SyncOutletAccess $sync): RedirectResponse
    {
        $assignments = [];

        foreach ((array) $request->validated('access', []) as $outletId => $userIds) {
            $assignments[(int) $outletId] = array_map(intval(...), (array) $userIds);
        }

        $sync->handle($this->propertyId(), array_map(intval(...), (array) $request->validated('users')), $assignments);

        return to_route('restaurant.access.index')->with('success', __('Outlet access saved.'));
    }

    private function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
