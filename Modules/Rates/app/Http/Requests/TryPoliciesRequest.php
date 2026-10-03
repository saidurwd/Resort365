<?php

namespace Modules\Rates\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Rates\Http\Requests\Concerns\UsesCurrentProperty;

/**
 * The "try it" box on the policies screen.
 */
class TryPoliciesRequest extends FormRequest
{
    use UsesCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('rates.policy.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $propertyId = $this->propertyId();

        return [
            'total' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'nights' => ['nullable', 'integer', 'min:1', 'max:60'],
            'percent' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'arrival' => ['nullable', 'date_format:Y-m-d'],
            'cancel_on' => ['nullable', 'date_format:Y-m-d'],
            'no_show' => ['boolean'],
            'deposit_policy' => ['nullable', 'integer', TenantRule::exists('deposit_policies')->where('property_id', $propertyId)->withoutTrashed()],
            'cancellation_policy' => ['nullable', 'integer', TenantRule::exists('cancellation_policies')->where('property_id', $propertyId)->withoutTrashed()],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['no_show' => $this->boolean('no_show')]);
    }
}
