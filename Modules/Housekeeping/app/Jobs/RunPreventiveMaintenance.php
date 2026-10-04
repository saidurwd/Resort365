<?php

namespace Modules\Housekeeping\Jobs;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Housekeeping\Actions\CreateDueWorkOrders;
use Modules\Property\Contracts\PropertyDirectory;
use Throwable;

/**
 * Preventive maintenance (ARCHITECTURE §5.11), scheduled daily: in every tenant that may use the
 * app, each active property opens the work orders of schedules due by its business date.
 */
class RunPreventiveMaintenance
{
    use Dispatchable;

    /**
     * @return int how many work orders were opened
     */
    public function handle(TenantContext $context, CreateDueWorkOrders $create): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $opened = 0;

        foreach (Tenant::query()->whereIn('status', $statuses)->orderBy('id')->get() as $tenant) {
            $opened += $context->run($tenant, function () use ($create): int {
                $count = 0;

                foreach (app(PropertyDirectory::class)->all() as $property) {
                    try {
                        $count += $create->handle($property->id, $property->businessDate);
                    } catch (Throwable $exception) {
                        report($exception);
                    }
                }

                return $count;
            });
        }

        return $opened;
    }
}
