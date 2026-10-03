<?php

namespace Modules\Rates\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Rates\DTOs\RestrictionSet;
use Modules\Rates\Http\Requests\Concerns\GridRangeRules;
use Modules\Rates\Models\RatePlan;

/**
 * Rate grid: set restrictions for a date range (this plan or all plans), or clear them.
 */
class SetRestrictionsRequest extends FormRequest
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
            'all_plans' => ['boolean'],
            'min_stay' => ['nullable', 'integer', 'min:1', 'max:60'],
            'max_stay' => ['nullable', 'integer', 'min:1', 'max:365', 'gte:min_stay'],
            'closed_to_arrival' => ['boolean'],
            'closed_to_departure' => ['boolean'],
            'stop_sell' => ['boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateUnits($validator);

            if ($this->input('action') === 'set' && $this->restrictions()->isEmpty()) {
                $validator->errors()->add('min_stay', __('Choose at least one restriction, or clear them.'));
            }
        }];
    }

    public function restrictions(): RestrictionSet
    {
        if ($this->input('action') === 'clear') {
            return new RestrictionSet;
        }

        return new RestrictionSet(
            $this->filled('min_stay') ? $this->integer('min_stay') : null,
            $this->filled('max_stay') ? $this->integer('max_stay') : null,
            $this->boolean('closed_to_arrival'),
            $this->boolean('closed_to_departure'),
            $this->boolean('stop_sell'),
        );
    }

    /**
     * Restrictions for every type use one row per date instead of one per type.
     */
    public function allUnits(): bool
    {
        return in_array('*', (array) $this->input('units', []), true);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'all_plans' => $this->boolean('all_plans'),
            'closed_to_arrival' => $this->boolean('closed_to_arrival'),
            'closed_to_departure' => $this->boolean('closed_to_departure'),
            'stop_sell' => $this->boolean('stop_sell'),
        ]);
    }
}
