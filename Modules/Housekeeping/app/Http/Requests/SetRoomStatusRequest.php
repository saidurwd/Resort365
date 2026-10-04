<?php

namespace Modules\Housekeeping\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Housekeeping\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Property\Enums\HousekeepingStatus;

/**
 * Bulk room status from the board: rooms of the current property and the new cleaning status.
 */
class SetRoomStatusRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('housekeeping.room.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_ids' => ['required', 'array', 'min:1'],
            'room_ids.*' => ['integer', $this->roomRule()],
            'status' => ['required', Rule::enum(HousekeepingStatus::class)],
        ];
    }
}
