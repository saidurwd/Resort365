<?php

namespace Modules\FrontOffice\Contracts;

/**
 * The blockers other modules register for the night audit (Restaurant: open POS sessions). Modules call
 * register() from their service provider with a class name, resolved when the audit is checked.
 */
final class NightAuditBlockers
{
    /** @var list<class-string<NightAuditBlocker>> */
    private array $blockers = [];

    /**
     * @param  class-string<NightAuditBlocker>  $blocker
     */
    public function register(string $blocker): void
    {
        if (! in_array($blocker, $this->blockers, true)) {
            $this->blockers[] = $blocker;
        }
    }

    /**
     * @return list<string>
     */
    public function blocking(int $propertyId, string $businessDate): array
    {
        return array_merge([], ...array_map(fn (string $blocker): array => app($blocker)->blocking($propertyId, $businessDate), $this->blockers));
    }
}
