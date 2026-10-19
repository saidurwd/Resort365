<?php

namespace Modules\HR\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\HR\Actions\SeedLeaveTypes;

/**
 * Gives tenants the standard leave types they lack (those created before Step 6.3); types they have are left alone.
 */
#[Description('Add the standard leave types and policies to tenants that lack them')]
#[Signature('hr:seed-leave-types {--tenant= : Only this tenant (slug)}')]
class SeedLeaveTypesCommand extends Command
{
    public function handle(TenantContext $context, SeedLeaveTypes $seed): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $added = $context->run($tenant, fn (): int => $seed->handle());
            $this->components->info("{$tenant->slug}: {$added} leave types added.");
        }

        return self::SUCCESS;
    }
}
