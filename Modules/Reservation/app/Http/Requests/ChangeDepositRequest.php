<?php

namespace Modules\Reservation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservation\Models\Reservation;

/**
 * A new deposit percent for a booking (0 waives it; outside the policy needs the override permission).
 */
class ChangeDepositRequest extends FormRequest
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
        return ['deposit_percent' => ['required', 'decimal:0,2', 'min:0', 'max:100']];
    }
}
