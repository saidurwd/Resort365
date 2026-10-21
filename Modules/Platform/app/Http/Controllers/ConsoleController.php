<?php

namespace Modules\Platform\Http\Controllers;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Platform\Models\Plan;
use Modules\Platform\Models\PlatformAuditEntry;

/**
 * The platform console's overview (Step 7.3): tenants by status and plan, trials about to end, the newest tenants and the latest
 * things admins did.
 */
class ConsoleController extends Controller
{
    public function __invoke(): View
    {
        $byStatus = Tenant::query()->selectRaw('status, COUNT(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('platform::console', [
            'counts' => array_map(fn (TenantStatus $status): int => (int) ($byStatus[$status->value] ?? 0), array_combine(array_map(fn (TenantStatus $s): string => $s->value, TenantStatus::cases()), TenantStatus::cases())),
            'total' => (int) $byStatus->sum(),
            'plans' => Plan::query()->withCount([])->orderBy('sort_order')->orderBy('name')->get()->map(fn (Plan $plan): array => ['plan' => $plan, 'tenants' => Tenant::query()->where('plan_id', $plan->id)->count()]),
            'noPlan' => Tenant::query()->whereNull('plan_id')->count(),
            'trials' => Tenant::query()->where('status', TenantStatus::Trial->value)->whereNotNull('trial_ends_at')->where('trial_ends_at', '<=', now()->addDays(14))->orderBy('trial_ends_at')->limit(10)->get(),
            'newest' => Tenant::query()->latest('id')->limit(5)->get(),
            'recent' => PlatformAuditEntry::query()->latest('id')->limit(8)->get(),
        ]);
    }
}
