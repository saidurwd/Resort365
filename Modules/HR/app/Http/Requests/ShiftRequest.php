<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.shift.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string', 'max:100'],
            'starts_at' => ['required', 'date_format:H:i'],
            'ends_at' => ['required', 'date_format:H:i'],
            'break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'overtime_after_minutes' => ['nullable', 'integer', 'min:0', 'max:240'],
            'color' => ['nullable', Rule::in(['primary', 'secondary', 'success', 'danger', 'warning', 'info'])],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
