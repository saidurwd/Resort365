<?php

namespace Modules\Housekeeping\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Housekeeping\Enums\BlockType;
use Modules\Housekeeping\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Housekeeping\Models\RoomBlock;

/**
 * Taking a room of the current property out of order or out of service for nights [from, to).
 */
class BlockRoomRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('create', RoomBlock::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['required', 'integer', $this->roomRule()],
            'type' => ['required', Rule::enum(BlockType::class)],
            'from_date' => ['required', 'date_format:Y-m-d'],
            'to_date' => ['required', 'date_format:Y-m-d', 'after:from_date'],
            'reason' => ['required', 'string', 'max:190'],
        ];
    }
}
