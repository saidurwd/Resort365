<?php

namespace Modules\Core\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Core\Actions\SeedApprovalWorkflows;

/**
 * Gives tenants the default approval workflow of every document type they lack (those created before the module that
 * owns it). Workflows they already have are left alone.
 */
#[Description('Add the default approval workflows to tenants that lack them')]
#[Signature('approvals:seed-defaults {--tenant= : Only this tenant (slug)}')]
class SeedApprovalsCommand extends Command
{
    public function handle(TenantContext $context, SeedApprovalWorkflows $seed): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $added = $context->run($tenant, fn (): int => $seed->handle());
            $this->components->info("{$tenant->slug}: {$added} workflows added.");
        }

        return self::SUCCESS;
    }
}
