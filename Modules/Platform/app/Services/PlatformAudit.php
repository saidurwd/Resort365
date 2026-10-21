<?php

namespace Modules\Platform\Services;

use Illuminate\Http\Request;
use Modules\Platform\Models\PlatformAdmin;
use Modules\Platform\Models\PlatformAuditEntry;

/**
 * The console's record of what admins did (Step 7.3).
 */
class PlatformAudit
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>|null  $details
     */
    public function log(?PlatformAdmin $admin, string $action, string $summary, ?int $tenantId = null, ?array $details = null): PlatformAuditEntry
    {
        return PlatformAuditEntry::query()->create([
            'platform_admin_id' => $admin?->id, 'admin_name' => $admin instanceof PlatformAdmin ? $admin->name : __('System'), 'tenant_id' => $tenantId, 'action' => $action, 'summary' => $summary, 'details' => $details, 'ip' => $this->request->ip(),
        ]);
    }
}
