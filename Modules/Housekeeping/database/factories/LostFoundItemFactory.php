<?php

namespace Modules\Housekeeping\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Housekeeping\Database\Factories\Concerns\ResolvesRoom;
use Modules\Housekeeping\Enums\LostItemStatus;
use Modules\Housekeeping\Models\LostFoundItem;

/**
 * @extends Factory<LostFoundItem>
 */
class LostFoundItemFactory extends Factory
{
    use ResolvesRoom;

    protected $model = LostFoundItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'property_id' => $this->propertyId(...),
            'found_on' => now()->toDateString(),
            'found_at' => 'Pool deck',
            'description' => 'Black sunglasses',
            'stored_at' => 'Front office safe',
            'status' => LostItemStatus::Stored,
        ];
    }
}
