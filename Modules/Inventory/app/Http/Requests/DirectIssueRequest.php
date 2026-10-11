<?php

namespace Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DirectIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.issue.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'department_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1', 'max:100'],
            'lines.*.item_id' => ['required', 'integer', TenantRule::exists('items')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
        ];
    }

    /**
     * @return list<array{item_id: int, quantity: string}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $row): array => ['item_id' => (int) $row['item_id'], 'quantity' => (string) $row['quantity']], (array) $this->validated('lines')));
    }
}
