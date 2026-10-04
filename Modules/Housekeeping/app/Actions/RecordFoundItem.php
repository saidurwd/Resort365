<?php

namespace Modules\Housekeeping\Actions;

use App\Support\Actions\Action;
use Modules\Housekeeping\Enums\LostItemStatus;
use Modules\Housekeeping\Models\LostFoundItem;

/**
 * Logs an item found on the property in the lost & found register.
 */
class RecordFoundItem extends Action
{
    /**
     * @param  array{found_on: string, room_id?: int|null, found_at: string, description: string, stored_at?: string|null, notes?: string|null}  $data
     */
    public function handle(int $propertyId, array $data, ?int $userId = null): LostFoundItem
    {
        return LostFoundItem::query()->create([
            'property_id' => $propertyId, 'found_on' => $data['found_on'], 'room_id' => $data['room_id'] ?? null, 'found_at' => $data['found_at'],
            'description' => $data['description'], 'stored_at' => $data['stored_at'] ?? null, 'notes' => $data['notes'] ?? null,
            'found_by' => $userId, 'status' => LostItemStatus::Stored,
        ]);
    }
}
