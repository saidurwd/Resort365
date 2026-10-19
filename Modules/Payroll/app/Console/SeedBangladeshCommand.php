<?php

namespace Modules\Payroll\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Payroll\Actions\SeedBangladeshPayroll;

/**
 * Gives tenants the Bangladesh payroll starter pack they lack (Step 6.4); what they already have is left alone.
 */
#[Description('Add the Bangladesh payroll starter pack (components, structures, income tax table) to tenants that lack it')]
#[Signature('payroll:seed-bangladesh {--tenant= : Only this tenant (slug)}')]
class SeedBangladeshCommand extends Command
{
    public function handle(TenantContext $context, SeedBangladeshPayroll $seed): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $made = $context->run($tenant, fn (): array => $seed->handle());
            $this->components->info("{$tenant->slug}: {$made['components']} components, {$made['structures']} structures, {$made['tax_tables']} tax tables added.");
        }

        return self::SUCCESS;
    }
}
