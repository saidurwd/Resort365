<?php

namespace Modules\Platform\Actions;

use App\Models\Tenant;
use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Models\ImpersonationSession;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Services\PlatformAudit;

/**
 * Prepares a platform admin's sign-in as a tenant user (Step 7.3, audited): for an active tenant and an active user, with a
 * reason. Returns the one-time link on the tenant's own subdomain; it works once, within a minute. What is done afterwards is
 * tagged with the admin in the tenant's own audit trail.
 */
class StartImpersonation extends Action
{
    public const int LINK_LIFETIME_SECONDS = 60;

    public function __construct(
        private readonly TenantContext $context,
        private readonly UserDirectory $users,
        private readonly PlatformAudit $audit,
        private readonly Request $request,
    ) {}

    /**
     * @throws PlatformRuleViolated
     */
    public function handle(PlatformAdmin $admin, Tenant $tenant, int $userId, string $reason): string
    {
        if (trim($reason) === '') {
            throw new PlatformRuleViolated(__('Say why you are signing in as this person.'));
        }

        if (! $tenant->status->canAccess()) {
            throw new PlatformRuleViolated(__(':tenant is :status, so nobody can sign in to it.', ['tenant' => $tenant->name, 'status' => mb_strtolower($tenant->status->label())]));
        }

        $user = $this->context->run($tenant, fn (): ?UserSummary => collect($this->users->all())->first(fn (UserSummary $summary): bool => $summary->id === $userId && $summary->status === 'active'));

        if (! $user instanceof UserSummary) {
            throw new PlatformRuleViolated(__('That person is not an active user of :tenant.', ['tenant' => $tenant->name]));
        }

        $token = Str::random(48);

        return $this->transaction(function () use ($admin, $tenant, $user, $reason, $token): string {
            $session = ImpersonationSession::query()->create([
                'platform_admin_id' => $admin->id, 'tenant_id' => $tenant->id, 'user_id' => $user->id, 'user_name' => $user->name, 'reason' => trim($reason), 'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addSeconds(self::LINK_LIFETIME_SECONDS), 'ip' => $this->request->ip(),
            ]);
            $this->audit->log($admin, 'impersonation.requested', __('Link made to sign in as :user', ['user' => $user->name]), $tenant->id, ['session' => $session->id, 'user_id' => $user->id, 'reason' => trim($reason)]);

            return $tenant->url('/iam/impersonate/'.$token);
        });
    }
}
