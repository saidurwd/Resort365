<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class WaiveInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.loan.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:300']];
    }
}
