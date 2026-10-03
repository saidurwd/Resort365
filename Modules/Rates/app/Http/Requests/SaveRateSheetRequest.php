<?php

namespace Modules\Rates\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Rates\Http\Requests\Concerns\UsesCurrentProperty;
use Modules\Rates\Models\RatePlan;

/**
 * rates[room_type:4][every|weekend][amount|extra_adult_amount|extra_child_amount]
 */
class SaveRateSheetRequest extends FormRequest
{
    use UsesCurrentProperty;

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
        $money = ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'];

        return [
            'rates' => ['array'],
            'rates.*.*.amount' => $money,
            'rates.*.*.extra_adult_amount' => $money,
            'rates.*.*.extra_child_amount' => $money,
        ];
    }

    /**
     * Only the plan's property's types, and only "every" and "weekend" sets.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $plan = $this->route('rate_plan');
            $units = $plan instanceof RatePlan ? $this->unitTypes($plan->property_id) : [];

            foreach ((array) $this->input('rates', []) as $key => $sets) {
                if (! isset($units[$key]) || array_diff(array_keys((array) $sets), ['every', 'weekend']) !== []) {
                    $validator->errors()->add('rates', __('Unknown room or cottage type.'));

                    return;
                }
            }
        }];
    }
}
