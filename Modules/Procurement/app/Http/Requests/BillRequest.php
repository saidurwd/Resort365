<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.bill.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'in:purchase,service'],
            'vendor_id' => ['required', 'integer', TenantRule::exists('vendors')],
            'purchase_order_id' => ['nullable', 'integer', TenantRule::exists('purchase_orders')],
            'bill_date' => ['nullable', 'date_format:Y-m-d'],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'vendor_reference' => ['nullable', 'string', 'max:60'],
            'department_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:300'],
            'override_match' => ['boolean'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.purchase_order_line_id' => ['nullable', 'integer', TenantRule::exists('purchase_order_lines')],
            'lines.*.description' => ['nullable', 'string', 'max:200'],
            'lines.*.expense_class' => ['nullable', 'in:food,beverage,housekeeping,maintenance,utilities,repairs,administrative,other'],
            'lines.*.quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.tax_category_id' => ['nullable', 'integer', TenantRule::exists('tax_categories')],
        ];
    }

    /**
     * Lines the person left empty (no quantity or price) are dropped.
     *
     * @return list<array<string, mixed>>
     */
    public function lines(): array
    {
        return array_values(array_filter(
            array_map(fn (array $row): array => [...$row, 'quantity' => (string) ($row['quantity'] ?? ''), 'unit_price' => (string) ($row['unit_price'] ?? '')], (array) $this->validated('lines')),
            fn (array $row): bool => $row['unit_price'] !== '' && $row['quantity'] !== '' && (float) $row['quantity'] > 0,
        ));
    }
}
