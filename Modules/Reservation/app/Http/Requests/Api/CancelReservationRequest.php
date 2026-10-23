<?php

namespace Modules\Reservation\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /api/v1/reservation/reservations/{id}/cancel` (Step 8.1): why. The fee follows the booking's cancellation policy.
 * Permission: `api.can:reservation.booking.cancel`.
 */
class CancelReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:500']];
    }
}
