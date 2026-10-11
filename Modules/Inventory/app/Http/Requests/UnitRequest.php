<?php

namespace Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\UnitKind;
use Modules\Inventory\Models\Unit;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.catalog.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $unit = $this->route('unit');

        return [
            'code' => ['required', 'string', 'max:12', 'alpha_dash:ascii', TenantRule::unique('units', 'code')->ignore($unit instanceof Unit ? $unit->id : null)],
            'name' => ['required', 'string', 'max:60'],
            'kind' => ['required', Rule::enum(UnitKind::class)],
            'base_unit_id' => ['nullable', 'integer', TenantRule::exists('units')],
            'factor' => ['nullable', 'numeric', 'gt:0', 'max:999999999999'],
            'is_active' => ['boolean'],
        ];
    }
}
