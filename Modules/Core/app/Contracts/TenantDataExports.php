<?php

namespace Modules\Core\Contracts;

use Modules\Core\DTOs\TenantExportSummary;

/**
 * Exports of all of the current tenant's data (Step 7.4): asked for by its owner on Settings → Subscription, or by a platform admin
 * from the console for a company that is leaving or has left.
 */
interface TenantDataExports
{
    /**
     * Queues a new export. Refused (ValidationException) while one is being prepared.
     *
     * @param  int|null  $userId  the tenant user who asked, told when it is ready; null for a platform admin
     */
    public function request(?int $userId, string $requestedByName): TenantExportSummary;

    /**
     * The latest exports, newest first.
     *
     * @return list<TenantExportSummary>
     */
    public function recent(int $limit = 5): array;

    /**
     * A ready export's file on its disk; null when it is unknown, not ready or expired.
     *
     * @return array{disk: string|null, path: string, name: string}|null
     */
    public function file(int $exportId): ?array;
}
