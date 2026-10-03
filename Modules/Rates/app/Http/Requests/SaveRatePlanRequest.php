<?php

namespace Modules\Rates\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Rates\Enums\BookingChannel;
use Modules\Rates\Enums\MealPlan;
use Modules\Rates\Http\Requests\Concerns\UsesCurrentProperty;
use Modules\Rates\Models\RatePlan;

class SaveRatePlanRequest extends FormRequest
{
    use UsesCurrentProperty;

    public function authorize(): bool
    {
        $plan = $this->route('rate_plan');

        return $plan instanceof RatePlan
            ? ($this->user()?->can('update', $plan) ?? false)
            : ($this->user()?->can('create', RatePlan::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $plan = $this->route('rate_plan');
        $plan = $plan instanceof RatePlan ? $plan : null;

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii',
                TenantRule::unique('rate_plans', 'code')->where('property_id', $this->propertyId($plan))->ignore($plan?->id)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meal_plan' => ['required', Rule::enum(MealPlan::class)],
            'meal_adult_amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'meal_child_amount' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'is_refundable' => ['boolean'],
            'prices_include_tax' => ['boolean'],
            'tax_category_id' => ['nullable', 'integer', TenantRule::exists('tax_categories')],
            'valid_from' => ['nullable', 'date_format:Y-m-d'],
            'valid_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => [Rule::enum(BookingChannel::class)],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'meal_adult_amount' => $this->input('meal_adult_amount') ?: '0',
            'meal_child_amount' => $this->input('meal_child_amount') ?: '0',
            'sort_order' => (int) $this->input('sort_order'),
            'is_refundable' => ! $this->has('is_refundable') || $this->boolean('is_refundable'),
            'prices_include_tax' => $this->boolean('prices_include_tax'),
            'is_active' => ! $this->has('is_active') || $this->boolean('is_active'),
        ]);
    }
}
