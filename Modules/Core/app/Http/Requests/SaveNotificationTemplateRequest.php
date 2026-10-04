<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The tenant's wording of a notification template. The subject is required for email.
 */
class SaveNotificationTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.notification-template.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(array_keys((array) config('app.available_locales')))],
            'subject' => [$this->route('channel') === 'mail' ? 'required' : 'nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'is_active' => ['boolean'],
        ];
    }
}
