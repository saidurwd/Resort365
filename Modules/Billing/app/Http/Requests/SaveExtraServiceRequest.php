<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveExtraServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.extra-service.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'charge_code_id' => ['required', 'integer', TenantRule::exists('charge_codes')->where('is_active', true)->withoutTrashed()],
            'unit' => ['nullable', 'string', 'max:30'],
            'unit_price' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'price_includes_tax' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
