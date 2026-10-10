<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Accounting\Enums\PeriodStatus;

/**
 * Closing, locking or reopening a fiscal period.
 */
class PeriodStatusRequest extends FormRequest
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
            'status' => ['required', Rule::enum(PeriodStatus::class)],
            'reason' => ['nullable', 'string', 'max:300'],
        ];
    }
}
