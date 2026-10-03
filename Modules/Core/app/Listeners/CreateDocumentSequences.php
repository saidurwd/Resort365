<?php

namespace Modules\Core\Listeners;

use App\Models\Tenant;
use App\Support\Tenancy\Events\PropertyCreated;
use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\DocumentNumbers;

/**
 * A new tenant (and each new property) gets every registered document sequence up front, so taking a number is always
 * a plain row lock (no first-time creation under concurrent load).
 */
class CreateDocumentSequences
{
    public function __construct(
        private readonly TenantContext $context,
        private readonly DocumentNumbers $numbers,
    ) {}

    public function handle(TenantCreated $event): void
    {
        $this->context->run($event->tenant, function (): void {
            $this->ensureAll(null);
        });
    }

    /**
     * A new property gets its own sequences too (property-level numbering).
     */
    public function handlePropertyCreated(PropertyCreated $event): void
    {
        $tenant = Tenant::query()->findOrFail($event->tenantId);

        $this->context->run($tenant, function () use ($event): void {
            $this->ensureAll($event->propertyId);
        });
    }

    private function ensureAll(?int $propertyId): void
    {
        foreach (array_keys($this->numbers->types()) as $type) {
            $this->numbers->ensure($type, $propertyId);
        }
    }
}
