<?php

namespace Modules\Platform\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Platform\Models\PlatformAuditEntry;

/**
 * Platform console → Audit log (Step 7.3): what every admin did, newest first, for a tenant or an action.
 */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $tenant = $request->filled('tenant') ? (int) $request->query('tenant') : null;
        $action = trim((string) $request->query('action', ''));

        return view('platform::audit.index', [
            'entries' => PlatformAuditEntry::query()->when($tenant !== null, fn ($query) => $query->where('tenant_id', $tenant))->when($action !== '', fn ($query) => $query->where('action', 'like', $action.'%'))->latest('id')->paginate(50)->withQueryString(),
            'tenant' => $tenant, 'action' => $action,
        ]);
    }
}
