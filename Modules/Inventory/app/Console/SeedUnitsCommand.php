<?php

namespace Modules\Inventory\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Inventory\Actions\SeedStandardUnits;

/**
 * Gives tenants the standard units of measure they lack (those created before the Inventory module).
 * Units they already have are left alone.
 */
#[Description('Add the standard units of measure to tenants that lack them')]
#[Signature('inventory:seed-units {--tenant= : Only this tenant (slug)}')]
class SeedUnitsCommand extends Command
{
    public function handle(TenantContext $context, SeedStandardUnits $seed): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $added = $context->run($tenant, fn (): int => $seed->handle());
            $this->components->info("{$tenant->slug}: {$added} units added.");
        }

        return self::SUCCESS;
    }
}
