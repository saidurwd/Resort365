<?php

namespace Modules\Procurement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VendorRequest extends FormRequest
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
            'code' => ['nullable', 'string', 'max:30'],
            'name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:300'],
            'currency' => ['required', 'string', 'size:3'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'status' => ['required', 'in:active,inactive'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
