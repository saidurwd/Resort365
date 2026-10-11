<?php

namespace Modules\Inventory\DTOs;

use App\Support\DTOs\Data;
use Modules\Inventory\Enums\MovementType;

/**
 * A stock movement to record (Step 5.3). `quantity` is positive, in `unitId` (default the item's base unit); the type
 * decides whether it adds to the store or takes from it. `unitCost` is per `unitId` unit: required for receipts and
 * openings, optional elsewhere (stock going out leaves at the average cost). Give an idempotency key when the caller may
 * repeat itself, and a source for the document that caused the movement.
 */
final readonly class NewStockMovement extends Data
{
    public function __construct(
        public MovementType $type,
        public int $storeId,
        public int $itemId,
        public string $quantity,
        public ?string $unitCost = null,
        public ?int $unitId = null,
        public ?string $date = null,
        public ?string $reason = null,
        public ?string $notes = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public ?string $sourceEvent = null,
        public ?string $idempotencyKey = null,
        public ?int $userId = null,
        public ?int $reverses = null,
        public ?bool $adds = null,
        public ?int $departmentId = null,
    ) {}
}
