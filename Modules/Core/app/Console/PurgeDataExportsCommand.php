<?php

namespace Modules\Core\Console;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use App\Support\Tenancy\TenantStorage;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Enums\TenantExportStatus;
use Modules\Core\Models\TenantExport;

/**
 * Deletes data export files after a week (Step 7.4). The row stays as a record, marked expired.
 */
#[Description('Delete data export files that have passed their expiry date')]
#[Signature('core:purge-data-exports')]
class PurgeDataExportsCommand extends Command
{
    public function handle(TenantContext $context, TenantStorage $storage): int
    {
        $purged = 0;

        foreach (Tenant::query()->get() as $tenant) {
            $context->run($tenant, function () use ($storage, &$purged): void {
                foreach (TenantExport::query()->where('status', TenantExportStatus::Ready->value)->where('expires_at', '<', now())->get() as $export) {
                    $storage->delete((string) $export->path);
                    $export->forceFill(['status' => TenantExportStatus::Expired, 'path' => null])->save();
                    $purged++;
                }
            });
        }

        $this->info(trans_choice(':count file deleted.|:count files deleted.', $purged));

        return self::SUCCESS;
    }
}
