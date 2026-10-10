<?php

namespace Modules\Accounting\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Accounting\Actions\BackpostHistory;

/**
 * Posts earlier operational events to the ledger (payments, closed days of charges, invoices…). Run it
 * after turning Accounting on for a tenant, or after fixing what blocked a posting.
 */
#[Description('Post earlier payments, charges and settlements to the general ledger')]
#[Signature('accounting:backpost {--tenant= : Only this tenant (slug)}')]
class BackpostCommand extends Command
{
    public function handle(TenantContext $context, BackpostHistory $backpost): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();
        $status = self::SUCCESS;

        foreach ($tenants as $tenant) {
            $result = $context->run($tenant, fn (): array => $backpost->handle());
            $this->components->info("{$tenant->slug}: {$result['posted']} entries posted.");

            foreach ($result['failed'] as $problem) {
                $this->components->warn($problem);
                $status = self::FAILURE;
            }
        }

        return $status;
    }
}
