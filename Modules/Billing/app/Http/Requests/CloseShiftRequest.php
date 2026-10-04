<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Billing\Models\CashierShift;

/**
 * Closing a cashier shift: how many of each note and coin were counted, and why the count is off
 * (checked by CloseShift, which knows the expected cash).
 */
class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');

        return $shift instanceof CashierShift && ($this->user()?->can('close', $shift) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'count' => ['required', 'array'],
            'count.*' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'variance_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
