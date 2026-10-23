<?php

namespace Modules\IAM\Http\Controllers;

use App\Support\Authorization\PermissionDefinition;
use App\Support\Authorization\PermissionRegistry;
use App\Support\Tenancy\PropertyAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\PlanLimits;
use Modules\IAM\Actions\CreateApiToken;
use Modules\IAM\Actions\RevokeApiToken;
use Modules\IAM\Http\Requests\CreateApiTokenRequest;
use Modules\IAM\Models\ApiToken;
use Modules\IAM\Models\User;

/**
 * Setup → API tokens (Step 8.1): your tokens (everyone's with `iam.api-token.manage`), a new one, and revoking.
 */
class ApiTokenController extends Controller
{
    public function __construct(private readonly PermissionRegistry $permissions) {}

    public function index(Request $request, PlanLimits $plan): View
    {
        Gate::authorize('viewAny', ApiToken::class);

        /** @var User $user */
        $user = $request->user();
        $all = $user->can('iam.api-token.manage');
        $tokens = ApiToken::query()
            ->when(! $all, fn ($query) => $query->where('tokenable_type', $user->getMorphClass())->where('tokenable_id', $user->id))
            ->with('tokenable')->latest('id')->get();
        $held = $user->getAllPermissions()->pluck('name')->all();

        return view('iam::api-tokens.index', [
            'tokens' => $tokens,
            'showOwner' => $all,
            'apiAllowed' => $plan->hasFeature('api_access'),
            'groups' => $this->groupsWithin($held),
            'properties' => app()->bound(PropertyAccess::class) ? app(PropertyAccess::class)->accessibleProperties($user) : [],
            'plainToken' => $request->session()->get('api_token_plain'),
        ]);
    }

    public function store(CreateApiTokenRequest $request, CreateApiToken $create): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        /** @var list<string> $abilities */
        $abilities = $request->validated('abilities');
        $token = $create->handle($user, (string) $request->string('name'), $abilities, $request->propertyIds(), $request->expiresAt(), $user->id);

        return to_route('iam.api-tokens.index')
            ->with('api_token_plain', $token->plainTextToken)
            ->with('success', __('Token ":name" created. Copy it now: it is not shown again.', ['name' => (string) $request->string('name')]));
    }

    public function destroy(ApiToken $apiToken, RevokeApiToken $revoke): RedirectResponse
    {
        Gate::authorize('delete', $apiToken);
        $revoke->handle($apiToken);

        return to_route('iam.api-tokens.index')->with('success', __('Token ":name" revoked.', ['name' => $apiToken->name]));
    }

    /**
     * The permission groups, keeping only the permissions the user holds (a token cannot have more).
     *
     * @param  list<string>  $held
     * @return array<string, array{label: string, permissions: list<PermissionDefinition>}>
     */
    private function groupsWithin(array $held): array
    {
        $groups = [];

        foreach ($this->permissions->grouped() as $module => $group) {
            $permissions = array_values(array_filter($group['permissions'], fn (PermissionDefinition $permission): bool => in_array($permission->name, $held, true)));

            if ($permissions !== []) {
                $groups[$module] = ['label' => $group['label'], 'permissions' => $permissions];
            }
        }

        return $groups;
    }
}
