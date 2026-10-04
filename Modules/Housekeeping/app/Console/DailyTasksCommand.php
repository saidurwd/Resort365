<?php

namespace Modules\Housekeeping\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Housekeeping\Actions\CreateDailyTasks;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;

#[Signature('housekeeping:daily-tasks')]
#[Description('Create the stayover tasks for rooms in house on each property\'s business date (also done after every night audit)')]
class DailyTasksCommand extends Command
{
    public function handle(TenantContext $context, CreateDailyTasks $tasks): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $rooms = 0;

        foreach (Tenant::query()->whereIn('status', $statuses)->orderBy('id')->get() as $tenant) {
            $rooms += $context->run($tenant, fn (): int => array_sum(array_map(fn (PropertySummary $property): int => $tasks->handle($property->id, $property->businessDate),
                app(PropertyDirectory::class)->all())));
        }

        $this->components->info("Rooms on today's round: {$rooms}.");

        return self::SUCCESS;
    }
}
