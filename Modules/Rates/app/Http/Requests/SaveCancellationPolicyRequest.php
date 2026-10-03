<?php

namespace Modules\Rates\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Rates\Enums\CancellationChargeType;
use Modules\Rates\Models\CancellationPolicy;

/**
 * rules[n][days_before_from|days_before_to|charge_type|charge_value]; tiers must not overlap.
 */
class SaveCancellationPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $policy = $this->route('cancellation_policy');

        return $policy instanceof CancellationPolicy
            ? ($this->user()?->can('update', $policy) ?? false)
            : ($this->user()?->can('create', CancellationPolicy::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'no_show_charge_type' => ['nullable', Rule::enum(CancellationChargeType::class)],
            'no_show_charge_value' => ['nullable', 'required_with:no_show_charge_type', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'is_default' => ['boolean'],
            'rules' => ['required', 'array', 'min:1', 'max:10'],
            'rules.*.days_before_from' => ['required', 'integer', 'min:0', 'max:730'],
            'rules.*.days_before_to' => ['nullable', 'integer', 'min:0', 'max:730', 'gte:rules.*.days_before_from'],
            'rules.*.charge_type' => ['required', Rule::enum(CancellationChargeType::class)],
            'rules.*.charge_value' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999'],
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $tiers = collect((array) $this->input('rules'))->sortBy(fn (array $rule): int => (int) $rule['days_before_from'])->values();

            foreach ($tiers as $i => $rule) {
                $percent = in_array($rule['charge_type'], [CancellationChargeType::PercentOfTotal->value, CancellationChargeType::PercentOfDeposit->value], true);

                if ($percent && (float) $rule['charge_value'] > 100) {
                    $validator->errors()->add('rules', __('A percentage cannot be more than 100.'));

                    return;
                }

                $previousTo = $i > 0 ? $tiers[$i - 1]['days_before_to'] ?? null : -1;

                if ($i > 0 && ($previousTo === null || $previousTo === '' || (int) $rule['days_before_from'] <= (int) $previousTo)) {
                    $validator->errors()->add('rules', __('The tiers must not overlap.'));

                    return;
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $rules = array_values(array_filter((array) $this->input('rules', []), fn (mixed $rule): bool => is_array($rule)
            && (($rule['days_before_from'] ?? '') !== '' || ($rule['charge_value'] ?? '') !== '')));

        $this->merge(['rules' => $rules, 'is_default' => $this->boolean('is_default'), 'no_show_charge_type' => $this->input('no_show_charge_type') ?: null]);
    }
}
