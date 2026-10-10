<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\RevenueClass;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * A menu category of the current property.
 */
class MenuCategoryRequest extends FormRequest
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
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:100'],
            'name.*' => ['nullable', 'string', 'max:100'],
            'parent_id' => ['nullable', 'integer', TenantRule::exists('menu_categories')->where('property_id', $this->propertyId())],
            'revenue_class' => ['nullable', Rule::enum(RevenueClass::class)],
            'colour' => ['required', 'in:primary,secondary,success,danger,warning,info'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['boolean'],
        ];
    }
}
