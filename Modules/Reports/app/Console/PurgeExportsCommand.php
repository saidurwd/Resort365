<?php

namespace Modules\Reports\Console;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Reports\Models\ReportExport;

/**
 * Deletes report files after a week (Step 7.1): the file, and the row's link to it. The row stays as a record.
 */
#[Description('Delete report export files that have passed their expiry date')]
#[Signature('reports:purge-exports')]
class PurgeExportsCommand extends Command
{
    public function handle(TenantContext $context, TenantStorage $storage): int
    {
        $purged = 0;

        foreach (Tenant::query()->get() as $tenant) {
            $context->run($tenant, function () use ($storage, &$purged): void {
                foreach (ReportExport::query()->whereNotNull('path')->where('expires_at', '<', now())->get() as $export) {
                    $storage->delete((string) $export->path);
                    $export->forceFill(['path' => null])->save();
                    $purged++;
                }
            });
        }

        $this->info(trans_choice(':count file deleted.|:count files deleted.', $purged));

        return self::SUCCESS;
    }
}
