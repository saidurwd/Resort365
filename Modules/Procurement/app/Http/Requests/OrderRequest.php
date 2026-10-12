<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.order.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', TenantRule::exists('vendors')],
            'store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'department_id' => ['nullable', 'integer'],
            'ordered_on' => ['nullable', 'date_format:Y-m-d'],
            'expected_on' => ['nullable', 'date_format:Y-m-d'],
            'payment_terms_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', 'integer', TenantRule::exists('items')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'lines.*.unit_price' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'lines.*.tax_category_id' => ['nullable', 'integer', TenantRule::exists('tax_categories')],
        ];
    }

    /**
     * @return list<array{item_id: int, quantity: string, unit_price: string, tax_category_id: int|null}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'], 'quantity' => (string) $row['quantity'], 'unit_price' => (string) $row['unit_price'], 'tax_category_id' => ($row['tax_category_id'] ?? '') !== '' ? (int) $row['tax_category_id'] : null,
        ], (array) $this->validated('lines')));
    }
}
