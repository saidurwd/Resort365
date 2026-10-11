<?php

namespace Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.stock.adjust') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'lines' => ['required', 'array', 'min:1', 'max:300'],
            'lines.*.item_id' => ['required', 'integer', TenantRule::exists('items')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'lines.*.unit_cost' => ['required', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return list<array{item_id: int, quantity: string, unit_cost: string}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $row): array => ['item_id' => (int) $row['item_id'], 'quantity' => (string) $row['quantity'], 'unit_cost' => (string) $row['unit_cost']], (array) $this->validated('lines')));
    }
}
