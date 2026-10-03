<?php

namespace Modules\Rates\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Rates\Enums\BalanceDueRule;
use Modules\Rates\Enums\DepositType;
use Modules\Rates\Models\DepositPolicy;

class SaveDepositPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $policy = $this->route('deposit_policy');

        return $policy instanceof DepositPolicy
            ? ($this->user()?->can('update', $policy) ?? false)
            : ($this->user()?->can('create', DepositPolicy::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $percent = ['decimal:0,2', 'min:0', 'max:100'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DepositType::class)],
            'min_percent' => ['nullable', ...$percent],
            'default_percent' => ['required', ...$percent],
            'max_percent' => ['nullable', ...$percent],
            'fixed_amount' => ['nullable', 'required_if:type,fixed_amount', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'due_within_minutes' => ['required', 'integer', 'min:1', 'max:43200'],
            'auto_cancel_unpaid' => ['boolean'],
            'balance_due_rule' => ['required', Rule::enum(BalanceDueRule::class)],
            'balance_due_days' => ['nullable', 'required_if:balance_due_rule,days_before_arrival', 'integer', 'min:1', 'max:365'],
            'full_payment_within_hours' => ['nullable', 'integer', 'min:1', 'max:8760'],
            'is_default' => ['boolean'],
        ];
    }

    /**
     * The default percent must sit inside the optional limits.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $default = (float) $this->input('default_percent');

            if (($this->filled('min_percent') && $default < (float) $this->input('min_percent'))
                || ($this->filled('max_percent') && $default > (float) $this->input('max_percent'))) {
                $validator->errors()->add('default_percent', __('The default must be between the minimum and the maximum.'));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'auto_cancel_unpaid' => ! $this->has('auto_cancel_unpaid') || $this->boolean('auto_cancel_unpaid'),
            'is_default' => $this->boolean('is_default'),
        ]);
    }
}
