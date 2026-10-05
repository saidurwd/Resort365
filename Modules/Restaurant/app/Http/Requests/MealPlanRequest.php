<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\MealPeriod;

/**
 * Redeeming a guest's meal plan on an order (JSON): the booking, the meal period and the covers.
 */
class MealPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.package.redeem') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reservation_id' => ['required', 'integer'],
            'period' => ['required', Rule::enum(MealPeriod::class)],
            'adults' => ['required', 'integer', 'min:0', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:50'],
            'approval_id' => ['nullable', 'integer'],
        ];
    }
}
