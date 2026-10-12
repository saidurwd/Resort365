<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AwardRequest extends FormRequest
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
            'quotation_id' => ['required', 'integer', TenantRule::exists('vendor_quotations')],
            'store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'expected_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
