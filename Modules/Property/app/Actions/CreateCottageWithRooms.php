<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Illuminate\Support\Arr;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Room;
use Modules\Property\Services\RoomNumberSequence;

/**
 * The quick "add cottage with N rooms" form (validated by CreateCottageRequest): creates the
 * cottage and its rooms, numbered on from the first room number, in one transaction.
 */
class CreateCottageWithRooms extends Action
{
    public function __construct(private readonly RoomNumberSequence $numbers) {}

    /**
     * @param  array<string, mixed>  $data  cottage fields plus room_count, room_type_id, first_room_number, floor
     */
    public function handle(array $data): Cottage
    {
        return $this->transaction(function () use ($data): Cottage {
            $cottage = Cottage::query()->create(Arr::except($data, ['room_count', 'room_type_id', 'first_room_number', 'floor']));

            foreach ($this->numbers->generate((string) $data['first_room_number'], (int) $data['room_count']) as $order => $number) {
                Room::query()->create([
                    'property_id' => $cottage->property_id,
                    'cottage_id' => $cottage->id,
                    'room_type_id' => $data['room_type_id'],
                    'number' => $number,
                    'floor' => $data['floor'] ?? null,
                    'is_active' => true,
                    'sort_order' => $order,
                ]);
            }

            return $cottage;
        });
    }
}
