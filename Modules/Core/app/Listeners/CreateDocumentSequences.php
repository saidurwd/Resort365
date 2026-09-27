<?php

namespace Modules\Core\Listeners;

use App\Support\Tenancy\Events\TenantCreated;
use App\Support\Tenancy\TenantContext;
use Modules\Core\Contracts\DocumentNumbers;

/**
 * A new tenant gets every registered document sequence up front, so taking a number is always
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
            foreach (array_keys($this->numbers->types()) as $type) {
                $this->numbers->ensure($type);
            }
        });
    }
}
