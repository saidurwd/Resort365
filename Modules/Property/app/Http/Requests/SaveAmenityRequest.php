<?php

namespace Modules\Property\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Property\Enums\AmenityCategory;
use Modules\Property\Models\Amenity;

class SaveAmenityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $amenity = $this->route('amenity');

        return $amenity instanceof Amenity
            ? ($this->user()?->can('update', $amenity) ?? false)
            : ($this->user()?->can('create', Amenity::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $amenity = $this->route('amenity');

        return [
            'name' => ['required', 'string', 'max:100', TenantRule::unique('amenities', 'name')->ignore($amenity instanceof Amenity ? $amenity->id : null)],
            'icon' => ['nullable', 'string', 'max:50', 'regex:/^bi-[a-z0-9-]+$/'],
            'category' => ['required', Rule::enum(AmenityCategory::class)],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['icon.regex' => __('Use a Bootstrap Icons class, e.g. bi-wifi.')];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['name' => trim((string) $this->input('name')), 'sort_order' => (int) $this->input('sort_order'), 'is_active' => ! $this->has('is_active') || $this->boolean('is_active')]);
    }
}
