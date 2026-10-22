<?php

namespace Modules\Core\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use App\Support\Tenancy\TenantStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Core\Contracts\ApprovalParticipants;
use Modules\Core\Enums\TenantExportStatus;
use Modules\Core\Models\TenantExport;
use Modules\Core\Notifications\DataExportNotice;
use Modules\Core\Services\TenantDataWriter;
use RuntimeException;
use Throwable;

/**
 * Makes a company's data export in the background (Step 7.4), as that tenant: the ZIP goes to the tenant's storage for a week and the
 * person who asked is told (an admin's export has nobody to tell; the console shows it).
 */
class GenerateTenantExport implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public const int KEEP_DAYS = 7;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $exportId)
    {
        // Long-running: the `long` queue, on a connection that waits long enough before retrying (Step 7.5).
        $this->onQueue('long');

        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-long');
        }
    }

    public function handle(TenantDataWriter $writer, TenantStorage $storage, ApprovalParticipants $people): void
    {
        $export = TenantExport::query()->find($this->exportId);

        if (! $export instanceof TenantExport || $export->status !== TenantExportStatus::Queued) {
            return;
        }

        $zip = (string) tempnam(sys_get_temp_dir(), 'tenant-export');

        try {
            $counts = $writer->write($export->tenant_id, $zip);
            $path = 'exports/'.$export->fileName();
            $stream = fopen($zip, 'r');

            if ($stream === false) {
                throw new RuntimeException('Cannot read '.$zip);
            }

            $storage->disk()->writeStream($storage->path($path), $stream);
            fclose($stream);

            $export->forceFill([
                'status' => TenantExportStatus::Ready, 'path' => $path, 'size' => (int) filesize($zip), 'tables_count' => $counts['tables'], 'rows_count' => $counts['rows'],
                'files_count' => $counts['files'], 'completed_at' => now(), 'expires_at' => now()->addDays(self::KEEP_DAYS), 'error' => null,
            ])->save();
            $this->tell($people, $export, true);
        } catch (Throwable $exception) {
            report($exception);
            $export->forceFill(['status' => TenantExportStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 500), 'completed_at' => now()])->save();
            $this->tell($people, $export, false);
        } finally {
            @unlink($zip);
        }
    }

    private function tell(ApprovalParticipants $people, TenantExport $export, bool $ready): void
    {
        if ($export->requested_by !== null) {
            $people->notify([$export->requested_by], new DataExportNotice($ready, route('core.subscription.show').'#data'));
        }
    }
}
