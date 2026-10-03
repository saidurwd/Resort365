<?php

namespace Modules\Guest\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Guest\Enums\IdType;
use Modules\Guest\Enums\VipLevel;
use Modules\Guest\Models\Guest;

class SaveGuestRequest extends FormRequest
{
    public const array TITLES = ['Mr', 'Mrs', 'Ms', 'Miss', 'Dr', 'Prof'];

    public function authorize(): bool
    {
        $guest = $this->route('guest');

        return $guest instanceof Guest
            ? ($this->user()?->can('update', $guest) ?? false)
            : ($this->user()?->can('create', Guest::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', Rule::in(self::TITLES)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{6,}$/'],
            'nationality_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'id_type' => ['nullable', Rule::enum(IdType::class)],
            'id_number' => ['nullable', 'required_with:id_expiry', 'string', 'max:50'],
            'id_expiry' => ['nullable', 'date'],
            'address' => ['nullable', 'array:line1,line2,city,state,postal_code,country_code'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['nullable', 'string', 'max:100'],
            'address.state' => ['nullable', 'string', 'max:100'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.country_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'company_id' => ['nullable', 'integer', TenantRule::exists('companies')->withoutTrashed()],
            'vip_level' => ['required', Rule::enum(VipLevel::class)],
            'preferences' => ['nullable', 'array', 'max:20'],
            'preferences.*' => ['string', 'max:50'],
            'marketing_consent' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'confirm_duplicate' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => __('Enter a phone number, e.g. 01711-000000 or +44 20 7946 0000.')];
    }

    /**
     * The validated guest fields (without the duplicate confirmation).
     *
     * @return array<string, mixed>
     */
    public function guestData(): array
    {
        return $this->safe()->except('confirm_duplicate');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'marketing_consent' => $this->boolean('marketing_consent'),
            'confirm_duplicate' => $this->boolean('confirm_duplicate'),
            'vip_level' => $this->input('vip_level') ?: VipLevel::None->value,
        ]);
    }
}
