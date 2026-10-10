<?php

namespace Modules\Accounting\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Accounting\Actions\SeedChartOfAccounts;

/**
 * Adds the starting chart of accounts to tenants that do not have it yet (those created before the
 * Accounting module), or the accounts a newer template added. Existing accounts are left alone.
 */
#[Description('Add the USALI-aligned chart of accounts to tenants that lack it')]
#[Signature('accounting:seed-chart {--tenant= : Only this tenant (slug)}')]
class SeedChartCommand extends Command
{
    public function handle(TenantContext $context, SeedChartOfAccounts $seed): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $added = $context->run($tenant, fn (): int => $seed->handle());
            $this->components->info("{$tenant->slug}: {$added} accounts added.");
        }

        return self::SUCCESS;
    }
}
