<?php

namespace Modules\Rates\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The prices step of the setup checklist (Step 7.4): a nightly price per room or cottage type, keyed "room_type:4".
 */
class RatesSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('rates.rate-plan.manage') && $this->user()->can('rates.rate.manage');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prices' => ['required', 'array'],
            'prices.*' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'],
        ];
    }
}
