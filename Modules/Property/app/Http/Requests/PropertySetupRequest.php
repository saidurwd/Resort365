<?php

namespace Modules\Property\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The first-property step of the setup checklist (Step 7.4): the few details a resort cannot start without.
 */
class PropertySetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('property.property.create');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:10', 'alpha_dash:ascii', TenantRule::unique('properties', 'code')],
            'country_code' => ['required', 'string', 'size:2', 'exists:countries,code'],
            'city' => ['nullable', 'string', 'max:100'],
            'address_line1' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'timezone' => ['required', 'string', 'exists:timezones,name'],
            'currency_code' => ['required', 'string', 'size:3', 'exists:currencies,code'],
            'check_in_time' => ['required', 'date_format:H:i'],
            'check_out_time' => ['required', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }
}
