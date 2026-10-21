<?php

namespace Modules\Core\Services;

use App\Support\Tenancy\TenantStorage;
use Carbon\CarbonImmutable;
use Modules\Core\Actions\RequestDataExport;
use Modules\Core\Contracts\TenantDataExports;
use Modules\Core\DTOs\TenantExportSummary;
use Modules\Core\Enums\TenantExportStatus;
use Modules\Core\Models\TenantExport;

class TenantDataExportService implements TenantDataExports
{
    public function __construct(private readonly TenantStorage $storage) {}

    public function request(?int $userId, string $requestedByName): TenantExportSummary
    {
        return $this->summary(RequestDataExport::make()->handle($userId, $requestedByName));
    }

    public function recent(int $limit = 5): array
    {
        return TenantExport::query()->latest('id')->limit($limit)->get()->map(fn (TenantExport $export): TenantExportSummary => $this->summary($export))->all();
    }

    public function file(int $exportId): ?array
    {
        $export = TenantExport::query()->find($exportId);

        if (! $export instanceof TenantExport || $export->status !== TenantExportStatus::Ready || $export->path === null || $export->expires_at?->isPast()) {
            return null;
        }

        return ['disk' => null, 'path' => $this->storage->path($export->path), 'name' => $export->fileName()];
    }

    private function summary(TenantExport $export): TenantExportSummary
    {
        return new TenantExportSummary(
            $export->id, $export->status->value, $export->status->label(), $export->status->color(), $export->requested_by_name, CarbonImmutable::instance($export->created_at),
            $export->expires_at !== null ? CarbonImmutable::instance($export->expires_at) : null, $export->size, $export->rows_count, $export->files_count,
        );
    }
}
