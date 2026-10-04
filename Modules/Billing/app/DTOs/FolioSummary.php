<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\FolioStatus;
use Modules\Billing\Enums\FolioType;

/**
 * A folio as other modules see it (FolioSettlement). balance is what is owed (decimal string).
 */
final readonly class FolioSummary extends Data
{
    public function __construct(
        public int $id,
        public string $folioNo,
        public FolioType $type,
        public string $name,
        public FolioStatus $status,
        public BillTo $billTo,
        public ?int $billToId,
        public string $currencyCode,
        public string $balance,
        public bool $hasCharges,
    ) {}
}
