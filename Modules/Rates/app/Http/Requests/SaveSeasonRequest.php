<?php

namespace Modules\Rates\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Rates\Enums\SeasonColor;
use Modules\Rates\Models\Season;

class SaveSeasonRequest extends FormRequest
{
    public function authorize(): bool
    {
        $season = $this->route('season');

        return $season instanceof Season
            ? ($this->user()?->can('update', $season) ?? false)
            : ($this->user()?->can('create', Season::class) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', Rule::enum(SeasonColor::class)],
            'priority' => ['required', 'integer', 'min:1', 'max:999'],
            'is_active' => ['boolean'],
            'periods' => ['required', 'array', 'min:1', 'max:20'],
            'periods.*.start_date' => ['required', 'date_format:Y-m-d'],
            'periods.*.end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:periods.*.start_date'],
        ];
    }

    /**
     * Periods of one season must not overlap.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $periods = collect((array) $this->input('periods'))->sortBy('start_date')->values();

            foreach ($periods as $i => $period) {
                if ($i > 0 && $period['start_date'] <= $periods[$i - 1]['end_date']) {
                    $validator->errors()->add('periods', __('The periods of a season must not overlap.'));

                    return;
                }
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $periods = array_values(array_filter((array) $this->input('periods', []), fn (mixed $period): bool => is_array($period)
            && (($period['start_date'] ?? '') !== '' || ($period['end_date'] ?? '') !== '')));

        $this->merge(['periods' => $periods, 'is_active' => ! $this->has('is_active') || $this->boolean('is_active')]);
    }
}
