<?php

namespace Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CountStartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.count.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'item_category_id' => ['nullable', 'integer', TenantRule::exists('item_categories')],
            'blind' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
