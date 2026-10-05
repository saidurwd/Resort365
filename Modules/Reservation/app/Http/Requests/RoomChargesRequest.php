<?php

namespace Modules\Reservation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Allow or block outlet charges to a booking's folio.
 */
class RoomChargesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservation.booking.update') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['no_room_charges' => ['required', 'boolean']];
    }
}
