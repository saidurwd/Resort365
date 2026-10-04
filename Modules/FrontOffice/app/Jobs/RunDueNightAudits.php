<?php

namespace Modules\FrontOffice\Jobs;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Bus\Dispatchable;
use Modules\Core\Contracts\Settings;
use Modules\FrontOffice\Actions\RunNightAudit;
use Modules\FrontOffice\Enums\NightAuditStatus;
use Modules\FrontOffice\Enums\NightAuditTrigger;
use Modules\FrontOffice\Exceptions\NightAuditNotPossible;
use Modules\FrontOffice\Models\NightAudit;
use Modules\FrontOffice\Notifications\NightAuditBlockedNotice;
use Modules\FrontOffice\Services\NightAuditSchedule;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Property\DTOs\PropertySummary;
use Throwable;

/**
 * The scheduled night audit (ARCHITECTURE §5.7), every five minutes: in every tenant
 * that may use the app, each active property with frontoffice.auto_night_audit on whose audit time
 * has come (NightAuditSchedule) is audited. A blocked audit tells the people who may run it, once per
 * date; it is tried again on the next run. One property that fails does not stop the others.
 */
class RunDueNightAudits
{
    use Dispatchable;

    /**
     * @param  int|null  $tenantId  only this tenant (the command's --tenant)
     * @param  int|null  $propertyId  only this property (the command's --property)
     * @param  bool  $force  run now, without waiting for the audit time (the command's --now)
     */
    public function __construct(
        public readonly ?int $tenantId = null,
        public readonly ?int $propertyId = null,
        public readonly bool $force = false,
    ) {}

    /**
     * @return list<NightAudit> the audits run
     */
    public function handle(TenantContext $context, RunNightAudit $audit, NightAuditSchedule $schedule): array
    {
        [$tenantId, $propertyId, $force] = [$this->tenantId, $this->propertyId, $this->force];

        $statuses = array_map(fn (TenantStatus $status): string => $status->value, array_filter(TenantStatus::cases(), fn (TenantStatus $status): bool => $status->canAccess()));
        $tenants = Tenant::query()->whereIn('status', $statuses)->when($tenantId !== null, fn ($query) => $query->whereKey($tenantId))->orderBy('id')->get();
        $audits = [];

        foreach ($tenants as $tenant) {
            $audits = [...$audits, ...$context->run($tenant, fn (): array => $this->forTenant($audit, $schedule, $propertyId, $force))];
        }

        return $audits;
    }

    /**
     * @return list<NightAudit>
     */
    private function forTenant(RunNightAudit $audit, NightAuditSchedule $schedule, ?int $propertyId, bool $force): array
    {
        $settings = app(Settings::class);
        $audits = [];

        foreach (app(PropertyDirectory::class)->all() as $property) {
            if ($propertyId !== null && $property->id !== $propertyId) {
                continue;
            }

            $due = $force || ($settings->get('frontoffice.auto_night_audit', $property->id)
                && $schedule->isDue($property->businessDate, (string) $settings->get('core.night_audit_time', $property->id), $property->timezone, CarbonImmutable::now()));

            if (! $due) {
                continue;
            }

            try {
                $result = $audit->handle($property->id, NightAuditTrigger::Scheduled);
                $audits[] = $result;

                if ($result->status === NightAuditStatus::Blocked && $result->notified_at === null) {
                    $this->notify($property, $result);
                }
            } catch (NightAuditNotPossible) {
                // Done or running already.
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $audits;
    }

    private function notify(PropertySummary $property, NightAudit $audit): void
    {
        $users = app(UserDirectory::class);
        $ids = array_values(array_map(fn (UserSummary $user): int => $user->id,
            array_filter($users->all(), fn (UserSummary $user): bool => $user->status === 'active' && $users->userCan($user->id, 'frontoffice.audit.run'))));

        $users->notify($ids, new NightAuditBlockedNotice($property->name, $audit->business_date->format('d M Y'), implode(' ', $audit->issues ?? []), route('frontoffice.night-audit.index')));
        $audit->forceFill(['notified_at' => now()])->save();
    }
}
