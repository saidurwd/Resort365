<?php

namespace Modules\Reservation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Booking wizard step 4: guests per item, promo code, deposit % and notes. action=recalculate
 * stays on the step; action=continue moves to the summary.
 */
class BookingPricingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservation.booking.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'occupancy' => ['array'],
            'occupancy.*.adults' => ['required', 'integer', 'min:1', 'max:50'],
            'occupancy.*.children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'promo_code' => ['nullable', 'string', 'max:30'],
            'deposit_percent' => ['nullable', 'decimal:0,2', 'min:0', 'max:100'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
            'group_name' => ['nullable', 'string', 'max:150'],
            'action' => ['required', 'in:recalculate,continue'],
        ];
    }
}
