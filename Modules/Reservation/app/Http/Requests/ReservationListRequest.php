<?php

namespace Modules\Reservation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * Filters of the reservation list: status, source and an arrival date range.
 */
class ReservationListRequest extends FormRequest
{
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
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'source' => ['nullable', Rule::enum(ReservationSource::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{status: string|null, source: string|null, from: string|null, to: string|null}
     */
    public function filters(): array
    {
        $value = fn (string $key): ?string => is_string($this->validated($key)) && $this->validated($key) !== '' ? $this->validated($key) : null;

        return ['status' => $value('status'), 'source' => $value('source'), 'from' => $value('from'), 'to' => $value('to')];
    }
}
