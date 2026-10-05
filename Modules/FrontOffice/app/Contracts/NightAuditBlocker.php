<?php

namespace Modules\FrontOffice\Contracts;

/**
 * Something another module needs done before a property's night audit may run (ARCHITECTURE §5.7), e.g.
 * the restaurant's POS sessions closed. Registered with NightAuditBlockers in the module's provider.
 */
interface NightAuditBlocker
{
    /**
     * Why the audit of the business date cannot run yet; none when it may.
     *
     * @return list<string>
     */
    public function blocking(int $propertyId, string $businessDate): array;
}
