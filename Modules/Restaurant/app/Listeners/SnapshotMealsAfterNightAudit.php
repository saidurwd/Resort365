<?php

namespace Modules\Restaurant\Listeners;

use Modules\FrontOffice\Events\NightAuditCompleted;
use Modules\Restaurant\Actions\SnapshotMealEntitlements;

/**
 * After a night audit, keep the meals the stays' plans included on the business date just audited.
 */
class SnapshotMealsAfterNightAudit
{
    public function __construct(
        private readonly SnapshotMealEntitlements $snapshot,
    ) {}

    public function handle(NightAuditCompleted $event): void
    {
        $this->snapshot->handle($event->propertyId, $event->businessDate);
    }
}
