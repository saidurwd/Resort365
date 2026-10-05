<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\VoidReason;

/**
 * Voiding a line sent to the kitchen (JSON): a reason, whether it was already made (wastage), and a
 * manager's approval when the person may not void.
 */
class VoidOrderLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.order.take') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(VoidReason::class)],
            'note' => ['nullable', 'required_if:reason,'.VoidReason::Other->value, 'string', 'max:300'],
            'wastage' => ['nullable', 'boolean'],
            'approval_id' => ['nullable', 'integer'],
        ];
    }
}
