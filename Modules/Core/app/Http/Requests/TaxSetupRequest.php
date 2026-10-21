<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TaxSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('core.onboarding.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_charge_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'vat_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'vat_on_service_charge' => ['boolean'],
        ];
    }
}
