<?php

namespace Modules\Core\DTOs;

use App\Support\DTOs\Data;
use Carbon\CarbonImmutable;

/**
 * One data export of a tenant (Step 7.4), for the tenant's page and the platform console.
 */
final readonly class TenantExportSummary extends Data
{
    public function __construct(
        public int $id,
        public string $status,
        public string $statusLabel,
        public string $statusColor,
        public string $requestedBy,
        public CarbonImmutable $requestedAt,
        public ?CarbonImmutable $expiresAt,
        public ?int $size,
        public ?int $rows,
        public ?int $files,
    ) {}

    public function ready(): bool
    {
        return $this->status === 'ready';
    }
}
