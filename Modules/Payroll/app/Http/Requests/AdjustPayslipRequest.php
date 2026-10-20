<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustPayslipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.run.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['earning', 'deduction'])],
            'name' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'note' => ['required', 'string', 'max:300'],
        ];
    }
}
