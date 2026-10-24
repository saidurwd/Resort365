<?php

namespace Modules\Website\Http\Requests;

use Carbon\CarbonImmutable;

/**
 * The stay fields shared by the website's search and cart forms (Step 8.2b): dates from today, at most 60 nights, and the party.
 */
trait StayRules
{
    /**
     * @return array<string, list<string>>
     */
    protected function stayRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';
        $checkIn = $this->input('check_in');
        $latest = is_string($checkIn) && strtotime($checkIn) !== false ? CarbonImmutable::parse($checkIn)->addDays(60)->toDateString() : '2999-12-31';

        return [
            'check_in' => [$presence, 'date_format:Y-m-d', 'after_or_equal:today'],
            'check_out' => [$required ? 'required' : 'required_with:check_in', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.$latest],
            'adults' => [$required ? 'required' : 'nullable', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'promo_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function stayMessages(): array
    {
        return ['check_out.before_or_equal' => __('A stay can be at most 60 nights.'), 'check_out.after' => __('Departure must be after arrival.')];
    }
}
