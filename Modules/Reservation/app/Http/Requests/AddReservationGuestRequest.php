<?php

namespace Modules\Reservation\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservation\Models\Reservation;

/**
 * A guest to add to a booking, optionally to one of its rooms or cottages.
 */
class AddReservationGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');

        return $reservation instanceof Reservation && ($this->user()?->can('update', $reservation) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $reservation = $this->route('reservation');

        return [
            'guest_id' => ['required', 'integer', TenantRule::exists('guests')->withoutTrashed()],
            'reservation_item_id' => ['nullable', 'integer', TenantRule::exists('reservation_items')->where('reservation_id', $reservation instanceof Reservation ? $reservation->id : 0)],
        ];
    }
}
