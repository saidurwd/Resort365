<?php

namespace Modules\Property\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cottages-and-rooms step of the setup checklist (Step 7.4).
 */
class RoomsSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('property.room.manage') && $this->user()->can('property.cottage.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cottage_count' => ['required', 'integer', 'min:1', 'max:100'],
            'rooms_per_cottage' => ['required', 'integer', 'min:1', 'max:20'],
            'room_type_name' => ['required', 'string', 'max:255'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:20'],
        ];
    }
}
