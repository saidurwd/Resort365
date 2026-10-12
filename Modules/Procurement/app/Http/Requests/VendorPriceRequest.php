<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VendorPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.vendor.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_id' => ['required', 'integer', TenantRule::exists('items')],
            'price' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'min_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'vendor_sku' => ['nullable', 'string', 'max:60'],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
