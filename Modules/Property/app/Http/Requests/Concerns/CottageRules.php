<?php

namespace Modules\Property\Http\Requests\Concerns;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Modules\Property\Enums\BookingMode;
use Modules\Property\Enums\CottageStatus;
use Modules\Property\Models\Cottage;

/**
 * Validation of a cottage's own fields (create and update).
 */
trait CottageRules
{
    use ScopedToProperty;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function cottageRules(?Cottage $cottage): array
    {
        $propertyId = $this->propertyId($cottage);

        return [
            'cottage_type_id' => ['required', 'integer', TenantRule::exists('cottage_types')->where('property_id', $propertyId)->withoutTrashed()],
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii',
                TenantRule::unique('cottages', 'code')->where('property_id', $propertyId)->ignore($cottage?->id)],
            'name' => ['required', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:100'],
            'booking_mode' => ['required', Rule::enum(BookingMode::class)],
            'max_occupancy_override' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::enum(CottageStatus::class)],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'description' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareCottage(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code'))), 'sort_order' => (int) $this->input('sort_order')]);
    }
}
