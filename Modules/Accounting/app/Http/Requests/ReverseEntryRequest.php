<?php

namespace Modules\Accounting\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reversing a posted journal entry: the date of the reversal (an open period) and a reason.
 */
class ReverseEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.journal.reverse') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:200'],
        ];
    }
}
