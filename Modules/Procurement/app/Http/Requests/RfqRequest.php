<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RfqRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.rfq.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'purchase_requisition_id' => ['required', 'integer', TenantRule::exists('purchase_requisitions')],
            'vendors' => ['required', 'array', 'min:2'],
            'vendors.*' => ['integer', TenantRule::exists('vendors')],
            'due_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:300'],
        ];
    }
}
