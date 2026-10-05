<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\Course;

/**
 * Adding an item to an order (JSON). The price is the server's; only an open item takes one.
 */
class OrderLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.order.take') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', TenantRule::exists('menu_items')],
            'variant_id' => ['nullable', 'integer', TenantRule::exists('menu_item_variants')],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
            'modifier_ids' => ['nullable', 'array', 'max:30'],
            'modifier_ids.*' => ['integer'],
            'course' => ['nullable', Rule::enum(Course::class)],
            'seat' => ['nullable', 'integer', 'min:1', 'max:50'],
            'notes' => ['nullable', 'string', 'max:300'],
            'held' => ['nullable', 'boolean'],
            'open_price' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
        ];
    }
}
