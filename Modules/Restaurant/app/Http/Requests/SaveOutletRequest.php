<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\OutletType;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Restaurant\Models\Outlet;

/**
 * An outlet of the current property: code and bill prefix unique per property, opening hours per weekday.
 */
class SaveOutletRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return ($outlet = $this->route('outlet')) instanceof Outlet ? ($this->user()?->can('update', $outlet) ?? false) : ($this->user()?->can('create', Outlet::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'alpha_dash', 'max:10', TenantRule::unique('outlets', 'code')->where('property_id', $this->propertyId())->ignore($this->route('outlet'))],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(OutletType::class)],
            'prices_include_tax' => ['boolean'],
            'default_tax_category_id' => ['nullable', 'integer', TenantRule::exists('tax_categories')],
            'bill_prefix' => ['required', 'alpha_num', 'max:10'],
            'receipt_header' => ['nullable', 'string', 'max:500'],
            'receipt_footer' => ['nullable', 'string', 'max:500'],
            'opening_hours' => ['nullable', 'array'],
            'opening_hours.*.open' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.close' => ['nullable', 'date_format:H:i', 'required_with:opening_hours.*.open'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
