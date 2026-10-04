<?php

namespace Modules\Reservation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The booking-source report's period (default: this month) and whether it counts bookings made or arrivals in it.
 */
class BookingSourceReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservation.report.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'by' => ['nullable', 'in:booked,arrival'],
        ];
    }
}
