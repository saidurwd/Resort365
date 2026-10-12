<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.requisition.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['nullable', 'integer'],
            'needed_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', 'integer', TenantRule::exists('items')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'lines.*.estimated_unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return list<array{item_id: int, quantity: string, estimated_unit_cost: string|null}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $row): array => [
            'item_id' => (int) $row['item_id'], 'quantity' => (string) $row['quantity'], 'estimated_unit_cost' => ($row['estimated_unit_cost'] ?? '') !== '' ? (string) $row['estimated_unit_cost'] : null,
        ], (array) $this->validated('lines')));
    }
}
