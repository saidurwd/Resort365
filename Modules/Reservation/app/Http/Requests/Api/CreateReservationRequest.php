<?php

namespace Modules\Reservation\Http\Requests\Api;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Guest\DTOs\GuestDetails;

/**
 * `POST /api/v1/reservation/reservations` (Step 8.1): the stay plus its guest, an existing one (`guest_id`) or a new one (`guest`).
 * Permission: `api.can:reservation.booking.create`; a new guest also needs `guest.guest.create`.
 */
class CreateReservationRequest extends BookingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** An existing guest… */
            'guest_id' => ['nullable', 'integer', 'required_without:guest', 'prohibits:guest', TenantRule::exists('guests')->withoutTrashed()],
            /** …or a new one, registered with the booking. */
            'guest' => ['nullable', 'array', 'required_without:guest_id'],
            'guest.first_name' => ['required_with:guest', 'string', 'max:100'],
            'guest.last_name' => ['nullable', 'string', 'max:100'],
            'guest.email' => ['nullable', 'email', 'max:255'],
            'guest.phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{6,}$/'],
            'guest.nationality_code' => ['nullable', 'string', 'size:2', 'exists:countries,code'],
        ];
    }

    public function newGuest(): ?GuestDetails
    {
        $guest = $this->validated('guest');

        if (! is_array($guest)) {
            return null;
        }

        $value = fn (string $key): ?string => isset($guest[$key]) && is_string($guest[$key]) && trim($guest[$key]) !== '' ? trim($guest[$key]) : null;

        return new GuestDetails((string) $value('first_name'), $value('last_name'), $value('phone'), $value('email'), nationalityCode: $value('nationality_code') !== null ? strtoupper($value('nationality_code')) : null);
    }
}
