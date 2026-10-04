<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * A modifier group of the current property and its options.
 */
class ModifierGroupRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.menu.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', TenantRule::unique('modifier_groups', 'name')->where('property_id', $this->propertyId())->ignore($this->route('group'))],
            'min_select' => ['required', 'integer', 'min:0', 'max:20'],
            'max_select' => ['required', 'integer', 'min:1', 'max:20'],
            'modifiers' => ['required', 'array', 'min:1', 'max:30'],
            'modifiers.*.id' => ['nullable', 'integer'],
            'modifiers.*.name' => ['required', 'string', 'max:100'],
            'modifiers.*.price_delta' => ['nullable', 'decimal:0,2', 'min:-9999999', 'max:9999999'],
            'modifiers.*.is_active' => ['boolean'],
        ];
    }
}
