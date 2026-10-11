<?php

namespace Modules\Core\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveApprovalWorkflowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.approval.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['boolean'],
            'steps' => ['nullable', 'array', 'max:6'],
            'steps.*.label' => ['required', 'string', 'max:100'],
            'steps.*.permission' => ['required', 'string', 'max:100'],
            'steps.*.min_amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:999999999999'],
        ];
    }
}
