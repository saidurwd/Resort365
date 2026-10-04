<?php

namespace Modules\Billing\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\Billing\Services\DefaultChargeCodes;

/**
 * A new tenant gets the default charge codes.
 */
class SeedChargeCodes
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DefaultChargeCodes $codes,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->context->run($event->tenant, fn () => $this->codes->ensure());
    }
}
