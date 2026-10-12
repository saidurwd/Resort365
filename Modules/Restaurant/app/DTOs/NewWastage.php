<?php

namespace Modules\Restaurant\DTOs;

use App\Support\DTOs\Data;
use Modules\Restaurant\Enums\WastageReason;

/**
 * Food or ingredients written off from a store (Step 5.7): raw `items` (item id and quantity) and/or `dishes` (menu item,
 * optional variant, portions), which are expanded through their recipes.
 */
final readonly class NewWastage extends Data
{
    /**
     * @param  list<array{item_id: int, quantity: string}>  $items
     * @param  list<array{menu_item_id: int, variant_id?: int|null, portions: string}>  $dishes
     */
    public function __construct(
        public int $storeId,
        public WastageReason $reason,
        public int $userId,
        public array $items = [],
        public array $dishes = [],
        public ?string $wastedOn = null,
        public ?string $notes = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
        public bool $allowNegative = false,
    ) {}
}
