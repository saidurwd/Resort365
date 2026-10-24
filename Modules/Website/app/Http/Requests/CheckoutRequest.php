<?php

namespace Modules\Website\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Guest\DTOs\GuestDetails;
use Modules\Website\Enums\PayChoice;

/**
 * The website's checkout (Step 8.2b): who is booking, what they pay now, and that they accept the policies. `website_url` is a
 * honeypot that people never see; a value in it is a robot.
 */
class CheckoutRequest extends FormRequest
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
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{6,}$/'],
            'nationality_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'pay_choice' => ['required', Rule::enum(PayChoice::class)],
            'accept_policies' => ['accepted'],
            'website_url' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a phone number, e.g. 01711-000000 or +44 20 7946 0000.'),
            'accept_policies.accepted' => __('Please accept the policies to book.'),
            'website_url.prohibited' => __('Please try again.'),
        ];
    }

    public function guest(): GuestDetails
    {
        $text = fn (string $key): ?string => is_string($this->validated($key)) && trim($this->validated($key)) !== '' ? trim($this->validated($key)) : null;

        return new GuestDetails((string) $text('first_name'), $text('last_name'), $text('phone'), strtolower((string) $text('email')),
            nationalityCode: $text('nationality_code') !== null ? strtoupper($text('nationality_code')) : null);
    }
}
