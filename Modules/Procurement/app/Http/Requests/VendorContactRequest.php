<?php

namespace Modules\Procurement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VendorContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.vendor.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['boolean'],
        ];
    }
}
