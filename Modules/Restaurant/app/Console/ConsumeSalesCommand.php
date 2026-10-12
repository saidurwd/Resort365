<?php

namespace Modules\Restaurant\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Modules\Restaurant\Actions\RecordBillConsumption;
use Modules\Restaurant\Enums\BillStatus;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\SaleConsumption;

/**
 * Takes the ingredients of settled bills that have none recorded out of stock (Step 5.7): bills settled before recipes
 * existed, or while the queue was down. Safe to repeat.
 */
#[Description('Record the stock consumed by settled bills that have no consumption yet')]
#[Signature('restaurant:consume-sales {--tenant= : Only this tenant (slug)} {--from= : Only bills from this business date (Y-m-d)}')]
class ConsumeSalesCommand extends Command
{
    public function handle(TenantContext $context, RecordBillConsumption $consume): int
    {
        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($this->option('tenant'), fn ($query, $slug) => $query->where('slug', $slug))->orderBy('id')->get();

        foreach ($tenants as $tenant) {
            $rows = $context->run($tenant, function () use ($consume): int {
                $done = SaleConsumption::query()->pluck('pos_bill_id')->unique()->all();
                $rows = 0;

                foreach (PosBill::query()->where('status', BillStatus::Settled->value)->when($this->option('from'), fn ($query, $from) => $query->where('business_date', '>=', $from))->whereNotIn('id', $done)->orderBy('id')->get() as $bill) {
                    $rows += $consume->handle($bill);
                }

                return $rows;
            });
            $this->components->info("{$tenant->slug}: {$rows} ingredient rows recorded.");
        }

        return self::SUCCESS;
    }
}
