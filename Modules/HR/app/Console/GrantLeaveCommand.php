<?php

namespace Modules\HR\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\HR\Actions\GrantLeave;

/**
 * Grants and accrues leave for the year (Step 6.3); safe to repeat. The scheduler runs it daily.
 */
#[Description('Grant and accrue the year\'s leave balances')]
#[Signature('hr:grant-leave {--tenant= : Only this tenant (slug)} {--year= : The year (default: this year)}')]
class GrantLeaveCommand extends Command
{
    public function handle(TenantContext $context, GrantLeave $grant): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();
        $today = CarbonImmutable::now();
        $year = $this->option('year') !== null ? (int) $this->option('year') : $today->year;

        foreach ($tenants as $tenant) {
            $count = $context->run($tenant, fn (): int => $grant->handle($year, $year < $today->year ? CarbonImmutable::create($year, 12, 31) : $today));
            $this->components->info("{$tenant->slug}: {$count} balances for {$year} created or updated.");
        }

        return self::SUCCESS;
    }
}
