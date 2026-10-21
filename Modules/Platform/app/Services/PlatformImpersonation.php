<?php

namespace Modules\Platform\Services;

use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\Impersonation;
use Modules\Core\DTOs\ImpersonationGrant;
use Modules\Platform\Models\ImpersonationSession;
use Modules\Platform\Models\PlatformAdmin;

/**
 * The tenant side of impersonation (Step 7.3): redeems the one-time link a platform admin made, and ends the session. A link works
 * once, within a minute, for the tenant it was made for.
 */
class PlatformImpersonation implements Impersonation
{
    public function __construct(
        private readonly TenantContext $tenant,
        private readonly PlatformAudit $audit,
    ) {}

    public function redeem(string $token): ?ImpersonationGrant
    {
        $session = ImpersonationSession::query()->where('token_hash', hash('sha256', $token))->first();
        $tenantId = $this->tenant->id();

        if (! $session instanceof ImpersonationSession || $session->tenant_id !== $tenantId || $session->started_at !== null || $session->expires_at->isPast()) {
            return null;
        }

        $admin = PlatformAdmin::query()->find($session->platform_admin_id);

        if (! $admin instanceof PlatformAdmin) {
            return null;
        }

        $session->forceFill(['started_at' => now()])->save();
        $this->audit->log($admin, 'impersonation.started', __('Signed in as :user', ['user' => $session->user_name]), $session->tenant_id, ['session' => $session->id, 'user_id' => $session->user_id, 'reason' => $session->reason]);

        return new ImpersonationGrant($session->id, $session->user_id, $admin->id, $admin->name);
    }

    public function end(int $sessionId): void
    {
        $session = ImpersonationSession::query()->find($sessionId);

        if ($session instanceof ImpersonationSession && $session->ended_at === null) {
            $session->forceFill(['ended_at' => now()])->save();
            $this->audit->log(PlatformAdmin::query()->find($session->platform_admin_id), 'impersonation.ended', __('Left :user\'s account', ['user' => $session->user_name]), $session->tenant_id, ['session' => $session->id]);
        }
    }

    public function exitUrl(): string
    {
        return route('platform.console');
    }
}
