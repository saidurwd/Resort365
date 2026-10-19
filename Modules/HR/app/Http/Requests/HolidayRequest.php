<?php

namespace Modules\HR\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('hr.holiday.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'holiday_date' => ['required', 'date_format:Y-m-d'],
            'is_paid' => ['nullable', 'boolean'],
            'repeats_yearly' => ['nullable', 'boolean'],
        ];
    }
}
