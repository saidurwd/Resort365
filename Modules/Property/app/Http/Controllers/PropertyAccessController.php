<?php

namespace Modules\Property\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Property\Actions\SyncPropertyAccess;
use Modules\Property\Http\Requests\UpdatePropertyAccessRequest;
use Modules\Property\Models\Property;
use Modules\Property\Services\PropertyAccessService;

/**
 * Users × properties grid. Users who see every property (property.property.access-all) are shown as such.
 */
class PropertyAccessController extends Controller
{
    public function __construct(
        private readonly UserDirectory $users,
        private readonly PropertyAccessService $access,
    ) {}

    public function index(): View
    {
        $users = $this->users->all();

        return view('property::access.index', [
            'users' => $users,
            'properties' => Property::query()->orderBy('name')->get(['id', 'code', 'name']),
            'assigned' => $this->access->assignments(),
            'seesAll' => collect($users)->mapWithKeys(fn ($user): array => [$user->id => $this->users->userCan($user->id, PropertyAccessService::ACCESS_ALL)])->all(),
        ]);
    }

    public function update(UpdatePropertyAccessRequest $request, SyncPropertyAccess $syncPropertyAccess): RedirectResponse
    {
        $assignments = [];
        foreach ((array) $request->validated('access', []) as $propertyId => $userIds) {
            $assignments[(int) $propertyId] = array_map(intval(...), (array) $userIds);
        }

        $syncPropertyAccess->handle(array_map(intval(...), (array) $request->validated('users')), $assignments);

        return to_route('property.access.index')->with('success', __('Property access saved.'));
    }
}
