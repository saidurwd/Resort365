<?php

namespace Modules\IAM\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\Attributes\ErrorBag;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Setting or removing one's POS PIN, confirmed with the current password.
 */
#[ErrorBag('posPin')]
class UpdatePosPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'current_password'],
            'pin' => ['exclude_if:remove,1', 'required', 'regex:/^\d{4,6}$/', 'confirmed'],
            'remove' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['pin.regex' => __('The PIN must be 4 to 6 digits.')];
    }
}
