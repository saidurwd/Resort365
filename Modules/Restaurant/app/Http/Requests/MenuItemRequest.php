<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\Allergen;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\DietaryTag;
use Modules\Restaurant\Enums\MenuItemKind;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Restaurant\Models\MenuItem;

/**
 * A menu item of the current property with its variants, modifier groups and combo components.
 */
class MenuItemRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return ($item = $this->route('item')) instanceof MenuItem ? ($this->user()?->can('update', $item) ?? false) : ($this->user()?->can('create', MenuItem::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'menu_category_id' => ['required', 'integer', TenantRule::exists('menu_categories')->where('property_id', $this->propertyId())],
            'code' => ['required', 'regex:/^[A-Za-z0-9_-]{1,20}$/', TenantRule::unique('menu_items', 'code')->where('property_id', $this->propertyId())->ignore($this->route('item'))],
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:150'],
            'name.*' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'array'],
            'description.*' => ['nullable', 'string', 'max:1000'],
            'course' => ['required', Rule::enum(Course::class)],
            'kind' => ['required', Rule::enum(MenuItemKind::class)],
            'tax_category_id' => ['nullable', 'integer', TenantRule::exists('tax_categories')],
            'dietary_tags' => ['nullable', 'array'],
            'dietary_tags.*' => [Rule::enum(DietaryTag::class)],
            'allergens' => ['nullable', 'array'],
            'allergens.*' => [Rule::enum(Allergen::class)],
            'variants' => ['nullable', 'array', 'max:10'],
            'variants.*' => ['nullable', 'string', 'max:60'],
            'modifier_group_ids' => ['nullable', 'array'],
            'modifier_group_ids.*' => ['integer', TenantRule::exists('modifier_groups')->where('property_id', $this->propertyId())],
            'components' => ['nullable', 'array', 'max:20'],
            'components.*.item_id' => ['required', 'integer', TenantRule::exists('menu_items')->where('property_id', $this->propertyId())],
            'components.*.variant_id' => ['nullable', 'integer', TenantRule::exists('menu_item_variants')],
            'components.*.quantity' => ['nullable', 'integer', 'min:1', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
        ];
    }
}
