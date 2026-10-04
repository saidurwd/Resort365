<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Billing\Models\CashierShift;

/**
 * Opening a cashier shift: the cash float in the drawer.
 */
class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('open', CashierShift::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'opening_float' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
        ];
    }
}
