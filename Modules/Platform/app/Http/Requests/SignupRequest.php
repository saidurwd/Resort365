<?php

namespace Modules\Platform\Http\Requests;

use App\Actions\Tenancy\CreateTenant;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Modules\Platform\Enums\BillingPeriod;

/**
 * Public sign-up on the central domain (Step 7.4). `website` is a field people never see: a form that fills it is a bot.
 */
class SignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company' => ['required', 'string', 'max:255'],
            'slug' => CreateTenant::rules()['slug'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'billing_period' => ['required', Rule::enum(BillingPeriod::class)],
            'terms' => ['accepted'],
            'website' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['slug.not_in' => __('The subdomain ":input" is reserved.'), 'slug.unique' => __('That address is taken. Try another.')];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['slug' => strtolower(trim((string) $this->input('slug'))), 'email' => strtolower(trim((string) $this->input('email')))]);
    }
}
