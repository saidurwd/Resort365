<?php

namespace Modules\FrontOffice\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Front Office → Flash report: which business date (GET).
 */
class FlashReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('frontoffice.report.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
