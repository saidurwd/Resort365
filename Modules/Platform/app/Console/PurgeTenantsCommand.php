<?php

namespace Modules\Platform\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Platform\Actions\PurgeTenantData;
use Throwable;

#[Description('Delete the data of tenants cancelled longer ago than the retention period (daily)')]
#[Signature('platform:purge-tenants {--dry-run : List what would be deleted, delete nothing}')]
class PurgeTenantsCommand extends Command
{
    public function handle(PurgeTenantData $purge): int
    {
        $due = Tenant::query()->where('status', TenantStatus::Cancelled->value)->whereNull('purged_at')
            ->where('cancelled_at', '<=', now()->subDays((int) config('platform.billing.retention_days')))->get();
        $failed = 0;

        foreach ($due as $tenant) {
            try {
                $counts = $purge->handle($tenant, (bool) $this->option('dry-run'));
                $this->info(__(':tenant: :rows rows in :tables tables:action', ['tenant' => $tenant->slug, 'rows' => array_sum($counts), 'tables' => count($counts), 'action' => $this->option('dry-run') ? ' '.__('would be deleted') : ' '.__('deleted')]));
            } catch (Throwable $exception) {
                report($exception);
                $this->error($tenant->slug.': '.$exception->getMessage());
                $failed++;
            }
        }

        if ($due->isEmpty()) {
            $this->info(__('No tenant is due for purging.'));
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
