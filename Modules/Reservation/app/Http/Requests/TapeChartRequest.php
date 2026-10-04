<?php

namespace Modules\Reservation\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reservations → Tape chart: the first day shown and how many days (GET).
 */
class TapeChartRequest extends FormRequest
{
    public const array WINDOWS = [14, 30];

    public function authorize(): bool
    {
        return $this->user()?->can('reservation.booking.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'in:'.implode(',', self::WINDOWS)],
        ];
    }

    /**
     * The first day shown: the one asked for, or the day before the business date (so last
     * night's arrivals and tonight's departures show).
     */
    public function from(CarbonImmutable $businessDate): CarbonImmutable
    {
        $from = $this->validated('from');

        return is_string($from) ? CarbonImmutable::createFromFormat('!Y-m-d', $from) ?: $businessDate->subDay() : $businessDate->subDay();
    }

    public function days(): int
    {
        return (int) ($this->validated('days') ?? self::WINDOWS[0]);
    }
}
