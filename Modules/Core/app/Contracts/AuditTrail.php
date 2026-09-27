<?php

namespace Modules\Core\Contracts;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Change history of a record, shaped for the <x-audit-trail> component.
 */
interface AuditTrail
{
    /**
     * Newest first.
     *
     * @return list<array{description: string, causer: ?string, at: CarbonInterface, changes: array<string, array{0: mixed, 1: mixed}>}>
     */
    public function for(Model $subject, int $limit = 50): array;
}
