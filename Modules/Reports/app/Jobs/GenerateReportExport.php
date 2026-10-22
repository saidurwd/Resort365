<?php

namespace Modules\Reports\Jobs;

use App\Support\Tenancy\InteractsWithTenant;
use App\Support\Tenancy\TenantAware;
use App\Support\Tenancy\TenantStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Reports\DTOs\ReportFilter;
use Modules\Reports\Enums\ExportStatus;
use Modules\Reports\Models\ReportExport;
use Modules\Reports\Notifications\ReportExportNotice;
use Modules\Reports\Services\ReportExporter;
use Modules\Reports\Services\ReportRunner;
use Throwable;

/**
 * Makes a report file in the background (Step 7.1), as the tenant that asked: runs the report for the filters it was asked
 * with, writes the file to the tenant's storage and tells the person who asked. Properties are in the filters, so the job needs no
 * signed-in user.
 */
class GenerateReportExport implements ShouldQueue, TenantAware
{
    use Dispatchable;
    use InteractsWithQueue;
    use InteractsWithTenant;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(public readonly int $exportId)
    {
        // Long-running: the `long` queue, on a connection that waits long enough before retrying (Step 7.5).
        $this->onQueue('long');

        if (config('queue.default') === 'redis') {
            $this->onConnection('redis-long');
        }
    }

    public function handle(ReportRunner $runner, ReportExporter $exporter, TenantStorage $storage, UserDirectory $users): void
    {
        $export = ReportExport::query()->find($this->exportId);

        if (! $export instanceof ReportExport || $export->status !== ExportStatus::Queued) {
            return;
        }

        $url = route('reports.exports.download', $export->id);

        try {
            $filter = ReportFilter::from($export->filters);
            $table = $runner->run($export->report_key, $filter);
            $path = 'reports/'.$export->id.'-'.$export->fileName();
            $storage->put($path, $exporter->make($table, $export->format, $this->subtitle($filter)));
            $export->forceFill(['status' => ExportStatus::Ready, 'path' => $path, 'rows_count' => count($table->rows), 'completed_at' => now(), 'expires_at' => now()->addDays(7), 'error' => null])->save();
            $users->notify([$export->user_id], new ReportExportNotice($export->title, $export->format->label(), $url, true));
        } catch (Throwable $exception) {
            report($exception);
            $export->forceFill(['status' => ExportStatus::Failed, 'error' => mb_substr($exception->getMessage(), 0, 500), 'completed_at' => now()])->save();
            $users->notify([$export->user_id], new ReportExportNotice($export->title, $export->format->label(), route('reports.exports.index'), false, mb_substr($exception->getMessage(), 0, 200)));
        }
    }

    private function subtitle(ReportFilter $filter): string
    {
        return __(':from to :to', ['from' => $filter->from, 'to' => $filter->to]).($filter->consolidated() ? ' · '.__('all properties') : '').' · '.now()->format('Y-m-d H:i');
    }
}
