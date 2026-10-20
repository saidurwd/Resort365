<?php

namespace Modules\Reports\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
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
            'property' => ['nullable', 'in:all'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'by' => ['nullable', 'in:booked,arrival'],
            'horizon' => ['nullable', 'integer', 'in:30,60,90'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'export' => ['nullable', 'in:csv,xlsx,pdf'],
        ];
    }
}
