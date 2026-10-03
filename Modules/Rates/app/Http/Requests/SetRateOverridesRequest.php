<?php

namespace Modules\Rates\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Rates\Http\Requests\Concerns\GridRangeRules;
use Modules\Rates\Models\RatePlan;

/**
 * Rate grid: set a price for a date range, or clear it (action=clear).
 */
class SetRateOverridesRequest extends FormRequest
{
    use GridRangeRules;

    public function authorize(): bool
    {
        $plan = $this->route('rate_plan');

        return $plan instanceof RatePlan && ($this->user()?->can('manageRates', $plan) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->rangeRules(),
            'action' => ['required', 'in:set,clear'],
            'amount' => ['required_if:action,set', 'nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [$this->validateUnits(...)];
    }

    public function amount(): ?string
    {
        return $this->input('action') === 'clear' ? null : (string) $this->input('amount');
    }
}
