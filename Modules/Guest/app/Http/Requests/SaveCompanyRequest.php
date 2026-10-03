<?php

namespace Modules\Guest\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Guest\Models\Company;

class SaveCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $company = $this->route('company');

        return $company instanceof Company
            ? ($this->user()?->can('update', $company) ?? false)
            : ($this->user()?->can('create', Company::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'array:line1,line2,city,state,postal_code,country_code'],
            'address.*' => ['nullable', 'string', 'max:255'],
            'credit_limit' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999.99'],
            'payment_terms_days' => ['required', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'credit_limit' => $this->input('credit_limit') ?: '0',
            'payment_terms_days' => (int) $this->input('payment_terms_days'),
            'is_active' => ! $this->has('is_active') || $this->boolean('is_active'),
        ]);
    }
}
