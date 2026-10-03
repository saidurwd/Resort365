<?php

namespace Modules\Property\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Http\Requests\Concerns\ScopedToProperty;
use Modules\Property\Models\RoomType;

class SaveRoomTypeRequest extends FormRequest
{
    use ScopedToProperty;

    public function authorize(): bool
    {
        $roomType = $this->route('room_type');

        return $roomType instanceof RoomType
            ? ($this->user()?->can('update', $roomType) ?? false)
            : ($this->user()?->can('create', RoomType::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $roomType = $this->route('room_type');
        $roomType = $roomType instanceof RoomType ? $roomType : null;

        return [
            'code' => ['required', 'string', 'max:10', 'alpha_dash:ascii',
                TenantRule::unique('room_types', 'code')->where('property_id', $this->propertyId($roomType))->ignore($roomType?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_occupancy' => ['required', 'integer', 'min:1', 'max:20', 'lte:max_occupancy'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:20', 'lte:max_occupancy'],
            'max_children' => ['required', 'integer', 'min:0', 'max:20'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:40'],
            'bed_configuration' => ['nullable', 'string', 'max:100'],
            'size_sqm' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'amenity_ids' => ['array'],
            'amenity_ids.*' => ['integer', TenantRule::exists('amenities')->withoutTrashed()],
        ];
    }

    /**
     * @return array<int, callable(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((int) $this->input('max_adults') + (int) $this->input('max_children') < (int) $this->input('max_occupancy')) {
                $validator->errors()->add('max_occupancy', __('Max occupancy cannot be more than max adults plus max children.'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code'))), 'sort_order' => (int) $this->input('sort_order')]);
        $this->normalizeFlags('is_active');
    }
}
