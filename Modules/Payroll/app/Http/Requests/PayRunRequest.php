<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payroll\Enums\PayMethod;

class PayRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.run.pay') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['payment_method' => ['required', Rule::enum(PayMethod::class)], 'paid_on' => ['required', 'date_format:Y-m-d'], 'payment_reference' => ['nullable', 'string', 'max:100']];
    }
}
