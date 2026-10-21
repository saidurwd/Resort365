<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Illuminate\Validation\ValidationException;
use Modules\Core\Enums\TenantExportStatus;
use Modules\Core\Jobs\GenerateTenantExport;
use Modules\Core\Models\TenantExport;

/**
 * Asks for an export of all of the current tenant's data (Step 7.4); the ZIP is made in the background. One at a time.
 */
class RequestDataExport extends Action
{
    /**
     * @throws ValidationException
     */
    public function handle(?int $userId, string $requestedByName): TenantExport
    {
        if (TenantExport::query()->where('status', TenantExportStatus::Queued->value)->exists()) {
            throw ValidationException::withMessages(['export' => __('An export is already being prepared.')]);
        }

        $export = TenantExport::query()->create(['requested_by' => $userId, 'requested_by_name' => $requestedByName, 'status' => TenantExportStatus::Queued]);
        activity()->performedOn($export)->log('Data export requested');

        GenerateTenantExport::dispatch($export->id);

        return $export;
    }
}
