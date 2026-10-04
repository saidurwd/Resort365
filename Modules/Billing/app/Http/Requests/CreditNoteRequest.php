<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.credit-note.issue') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'reason' => ['required', 'string', 'max:190'],
        ];
    }
}
