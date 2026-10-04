<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A menu CSV to import into the current property.
 */
class MenuImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.menu.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ];
    }
}
