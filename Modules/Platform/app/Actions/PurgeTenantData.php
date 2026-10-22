<?php

namespace Modules\Platform\Actions;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Modules\Platform\Exceptions\PlatformRuleViolated;
use Modules\Platform\Services\PlatformAudit;

/**
 * Deletes a cancelled tenant's data once its retention period is over (Step 7.5, ARCHITECTURE §5.2): every row with its `tenant_id`
 * in every table, and its files on each disk. The platform keeps what it needs about the tenant — the tenant row itself (a
 * tombstone; the subdomain stays taken), its subscriptions, platform invoices and payments, and the console's audit log. Deleting
 * personal data is the point, so no copy is kept; owners and admins can export the data at any time before. Cannot be undone.
 */
class PurgeTenantData extends Action
{
    /** Central tables that keep the tenant's id for the platform's own records. */
    public const array KEPT = ['subscriptions', 'platform_invoices', 'platform_payments', 'platform_audit_log', 'impersonation_sessions'];

    public function __construct(private readonly PlatformAudit $audit) {}

    /**
     * @return array<string, int> table => rows deleted (or that would be, on a dry run)
     *
     * @throws PlatformRuleViolated
     */
    public function handle(Tenant $tenant, bool $dryRun = false): array
    {
        $retention = (int) config('platform.billing.retention_days');

        if ($tenant->status !== TenantStatus::Cancelled || $tenant->cancelled_at === null || $tenant->cancelled_at->gt(now()->subDays($retention))) {
            throw new PlatformRuleViolated(__(':tenant has not been cancelled for :days days.', ['tenant' => $tenant->name, 'days' => $retention]));
        }

        if ($tenant->purged_at !== null) {
            throw new PlatformRuleViolated(__(':tenant was already purged.', ['tenant' => $tenant->name]));
        }

        $counts = [];

        foreach (self::tables() as $table) {
            $counts[$table] = DB::table($table)->where('tenant_id', $tenant->id)->count();
        }

        $counts = array_filter($counts);

        if ($dryRun) {
            return $counts;
        }

        // Rows of one tenant refer to each other in every direction, so the foreign keys are checked off while they all go.
        Schema::withoutForeignKeyConstraints(function () use ($counts, $tenant): void {
            foreach (array_keys($counts) as $table) {
                DB::table($table)->where('tenant_id', $tenant->id)->delete();
            }
        });

        foreach (array_unique(['local', (string) config('media-library.disk_name')]) as $disk) {
            Storage::disk($disk)->deleteDirectory('tenants/'.$tenant->id);
        }

        $tenant->forceFill(['purged_at' => now()])->save();
        $this->audit->log(null, 'tenant.purged', __('Data of :tenant deleted after the retention period', ['tenant' => $tenant->name]), $tenant->id, ['rows' => array_sum($counts), 'tables' => count($counts)]);

        return $counts;
    }

    /**
     * Every table with a `tenant_id` column, except the platform's own.
     *
     * @return list<string>
     */
    public static function tables(): array
    {
        $tables = Schema::getTableListing(Schema::getCurrentSchemaListing(), false);

        return array_values(array_filter($tables, fn (string $table): bool => ! in_array($table, self::KEPT, true) && Schema::hasColumn($table, 'tenant_id')));
    }
}
