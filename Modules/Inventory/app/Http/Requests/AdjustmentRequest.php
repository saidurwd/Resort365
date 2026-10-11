<?php

namespace Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\AdjustmentReason;

class AdjustmentRequest extends FormRequest
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
            'item_id' => ['required', 'integer', TenantRule::exists('items')],
            'direction' => ['required', 'in:gain,loss'],
            'quantity' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'unit_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
