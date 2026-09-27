<?php

namespace Modules\IAM\Http\Controllers;

use App\Support\Authorization\PermissionRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\IAM\Actions\DeleteRole;
use Modules\IAM\Actions\SaveRole;
use Modules\IAM\Http\Requests\SaveRoleRequest;
use Modules\IAM\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly PermissionRegistry $permissions) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Role::class);

        $roles = Role::query()->withCount(['users', 'permissions'])->orderByDesc('is_system')->orderBy('id')->get();

        return view('iam::roles.index', ['roles' => $roles, 'permissionCount' => count($this->permissions->names())]);
    }

    public function create(): View
    {
        Gate::authorize('create', Role::class);

        return view('iam::roles.form', ['role' => null, 'granted' => [], 'groups' => $this->permissions->grouped()]);
    }

    public function store(SaveRoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        $role = $saveRole->handle(null, (string) $request->string('name'), $request->validated('description'), $this->permissionNames($request));

        return to_route('iam.roles.index')->with('success', __('Role ":name" created.', ['name' => $role->name]));
    }

    public function show(Role $role): View
    {
        Gate::authorize('view', $role);

        return view('iam::roles.show', [
            'role' => $role,
            'granted' => $role->permissions()->pluck('name')->all(),
            'groups' => $this->permissions->grouped(),
            'history' => app(AuditTrail::class)->for($role),
        ]);
    }

    public function edit(Role $role): View
    {
        Gate::authorize('update', $role);

        return view('iam::roles.form', [
            'role' => $role,
            'granted' => $role->permissions()->pluck('name')->all(),
            'groups' => $this->permissions->grouped(),
            'history' => app(AuditTrail::class)->for($role),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        $saveRole->handle($role, (string) $request->string('name'), $request->validated('description'), $this->permissionNames($request));

        return to_route('iam.roles.index')->with('success', __('Role ":name" saved.', ['name' => $role->name]));
    }

    public function destroy(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        Gate::authorize('delete', $role);

        $deleteRole->handle($role);

        return to_route('iam.roles.index')->with('success', __('Role ":name" deleted.', ['name' => $role->name]));
    }

    /**
     * @return list<string>
     */
    private function permissionNames(SaveRoleRequest $request): array
    {
        return array_values(array_map(strval(...), (array) $request->validated('permissions', [])));
    }
}
