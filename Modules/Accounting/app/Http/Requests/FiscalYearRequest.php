<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening a fiscal year of twelve monthly periods.
 */
class FiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.period.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'name' => ['nullable', 'string', 'max:60'],
        ];
    }
}
