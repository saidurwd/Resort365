<?php

namespace Modules\Payroll\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Payroll\Enums\PoolKind;
use Modules\Payroll\Enums\PoolRule;

class PoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.pool.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'month' => ['required', 'date_format:Y-m'],
            'kind' => ['required', Rule::enum(PoolKind::class)],
            'rule' => ['nullable', Rule::enum(PoolRule::class)],
            'staff_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ];
    }
}
