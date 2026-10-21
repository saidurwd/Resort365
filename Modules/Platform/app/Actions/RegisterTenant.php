<?php

namespace Modules\Platform\Actions;

use App\Actions\Tenancy\CreateTenant;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Support\Actions\Action;
use App\Support\Tenancy\TenantContext;
use Modules\IAM\Contracts\OwnerAccounts;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Models\Plan;
use Modules\Platform\Services\PlatformAudit;
use Throwable;

/**
 * Self-service sign-up (Step 7.4): creates the company with every module's defaults (roles, chart of accounts, document sequences…),
 * puts it on a free trial of the chosen plan and makes its Tenant Owner; if that fails, the new company is closed again. Returns the tenant and the one-time link that
 * signs the owner in on the new subdomain, where the setup checklist waits.
 */
class RegisterTenant extends Action
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly OwnerAccounts $owners,
        private readonly PlatformAudit $audit,
    ) {}

    /**
     * @param  array{company: string, slug: string, name: string, email: string, password: string}  $data
     * @return array{tenant: Tenant, link: string}
     */
    public function handle(array $data, Plan $plan, BillingPeriod $period): array
    {
        // Its own commit first: the modules seed the company's defaults (roles among them) once the tenant is committed.
        $tenant = CreateTenant::make()->handle($data['slug'], $data['company'], $data['email'], TenantStatus::Trial);

        try {
            $ownerId = $this->transaction(function () use ($data, $plan, $period, $tenant): int {
                StartTrial::make()->handle($tenant, $plan, $period);
                $ownerId = $this->context->run($tenant, fn (): int => $this->owners->createOwner($data['name'], $data['email'], $data['password']));
                $this->audit->log(null, 'tenant.signed_up', __(':tenant signed up for a trial of the :plan plan', ['tenant' => $tenant->name, 'plan' => $plan->name]), $tenant->id, ['plan' => $plan->id]);

                return $ownerId;
            });
        } catch (Throwable $exception) {
            // A half-made company must not stay open; the platform admin sees it as cancelled in the console.
            $tenant->forceFill(['status' => TenantStatus::Cancelled, 'cancelled_at' => now()])->save();

            throw $exception;
        }

        return ['tenant' => $tenant, 'link' => $this->context->run($tenant, fn (): string => $this->owners->welcomeLink($ownerId))];
    }
}
