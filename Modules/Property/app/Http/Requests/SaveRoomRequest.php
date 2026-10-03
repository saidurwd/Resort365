<?php

namespace Modules\Property\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Http\Requests\Concerns\ScopedToProperty;
use Modules\Property\Models\Room;

/**
 * A room belongs to a cottage and a room type of its own property; numbers are unique per
 * property, including deleted rooms (the database index counts them too).
 */
class SaveRoomRequest extends FormRequest
{
    use ScopedToProperty;

    public function authorize(): bool
    {
        $room = $this->route('room');

        return $room instanceof Room
            ? ($this->user()?->can('update', $room) ?? false)
            : ($this->user()?->can('create', Room::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $room = $this->route('room');
        $room = $room instanceof Room ? $room : null;
        $propertyId = $this->propertyId($room);

        return [
            'cottage_id' => ['required', 'integer', TenantRule::exists('cottages')->where('property_id', $propertyId)->withoutTrashed()],
            'room_type_id' => ['required', 'integer', TenantRule::exists('room_types')->where('property_id', $propertyId)->withoutTrashed()],
            'number' => ['required', 'string', 'max:20', 'alpha_dash:ascii',
                TenantRule::unique('rooms', 'number')->where('property_id', $propertyId)->ignore($room?->id)],
            'name' => ['nullable', 'string', 'max:255'],
            'floor' => ['nullable', 'string', 'max:20'],
            'max_adults' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['number.unique' => __('This room number is already used in this property (deleted rooms keep their numbers).')];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['number' => strtoupper(trim((string) $this->input('number'))), 'sort_order' => (int) $this->input('sort_order')]);
        $this->normalizeFlags('is_active');
    }
}
