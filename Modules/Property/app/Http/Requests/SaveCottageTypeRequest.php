<?php

namespace Modules\Property\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Http\Requests\Concerns\ScopedToProperty;
use Modules\Property\Models\CottageType;

class SaveCottageTypeRequest extends FormRequest
{
    use ScopedToProperty;

    public function authorize(): bool
    {
        $cottageType = $this->route('cottage_type');

        return $cottageType instanceof CottageType
            ? ($this->user()?->can('update', $cottageType) ?? false)
            : ($this->user()?->can('create', CottageType::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $cottageType = $this->route('cottage_type');
        $cottageType = $cottageType instanceof CottageType ? $cottageType : null;

        return [
            'code' => ['required', 'string', 'max:10', 'alpha_dash:ascii',
                TenantRule::unique('cottage_types', 'code')->where('property_id', $this->propertyId($cottageType))->ignore($cottageType?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'max_occupancy' => ['required', 'integer', 'min:1', 'max:100'],
            'bedrooms' => ['required', 'integer', 'min:1', 'max:50'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'amenity_ids' => ['array'],
            'amenity_ids.*' => ['integer', TenantRule::exists('amenities')->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code'))), 'sort_order' => (int) $this->input('sort_order')]);
        $this->normalizeFlags('is_active');
    }
}
