<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\WastageReason;

class WastageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.wastage.record') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'reason' => ['required', Rule::enum(WastageReason::class)],
            'wasted_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:300'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.item_id' => ['nullable', 'integer', TenantRule::exists('items')],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'dishes' => ['nullable', 'array', 'max:50'],
            'dishes.*.menu_item_id' => ['nullable', 'integer', TenantRule::exists('menu_items')],
            'dishes.*.portions' => ['nullable', 'numeric', 'min:0', 'max:99999'],
        ];
    }

    /**
     * @return list<array{item_id: int, quantity: string}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $row): array => ['item_id' => (int) $row['item_id'], 'quantity' => (string) $row['quantity']],
            array_filter((array) $this->validated('items', []), fn (array $row): bool => ($row['item_id'] ?? '') !== '' && ($row['quantity'] ?? '') !== '')));
    }

    /**
     * @return list<array{menu_item_id: int, portions: string}>
     */
    public function dishes(): array
    {
        return array_values(array_map(fn (array $row): array => ['menu_item_id' => (int) $row['menu_item_id'], 'portions' => (string) $row['portions']],
            array_filter((array) $this->validated('dishes', []), fn (array $row): bool => ($row['menu_item_id'] ?? '') !== '' && ($row['portions'] ?? '') !== '')));
    }
}
