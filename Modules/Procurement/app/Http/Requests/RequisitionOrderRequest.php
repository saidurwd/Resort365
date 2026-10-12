<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RequisitionOrderRequest extends FormRequest
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
            'purchase_requisition_id' => ['required', 'integer', TenantRule::exists('purchase_requisitions')],
            'vendor_id' => ['required', 'integer', TenantRule::exists('vendors')],
            'store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'expected_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
