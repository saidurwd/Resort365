<?php

namespace Modules\IAM\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Language and colour mode for the signed-in user (both optional, so the navbar toggle can send only the theme).
 */
class UpdatePreferencesRequest extends FormRequest
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
            'locale' => ['sometimes', 'required', 'string', Rule::in(array_keys((array) config('app.available_locales')))],
            'theme' => ['sometimes', 'required', 'string', Rule::in(['light', 'dark', 'auto'])],
        ];
    }
}
