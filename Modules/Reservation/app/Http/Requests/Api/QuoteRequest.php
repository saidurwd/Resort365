<?php

namespace Modules\Reservation\Http\Requests\Api;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `POST /api/v1/reservation/quotes` (Step 8.1): prices a stay. With `save` it is kept as a quote for the guest (`guest_id`), with a
 * number and a validity date; nothing is held either way. Permission: `api.can:reservation.quote.create`.
 */
class QuoteRequest extends BookingRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            /** Keep the quote (needs `guest_id`); otherwise only the price is answered. */
            'save' => ['boolean'],
            'guest_id' => ['nullable', 'integer', 'required_if_accepted:save', TenantRule::exists('guests')->withoutTrashed()],
        ];
    }
}
