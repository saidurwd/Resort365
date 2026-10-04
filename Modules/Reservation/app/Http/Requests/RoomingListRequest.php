<?php

namespace Modules\Reservation\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservation\Models\Reservation;

/**
 * Rooming list rows: per item, an existing guest or a new guest's name.
 */
class RoomingListRequest extends FormRequest
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
        return [
            'rows' => ['required', 'array'],
            'rows.*.guest_id' => ['nullable', 'integer', TenantRule::exists('guests')->withoutTrashed()],
            'rows.*.new_name' => ['nullable', 'string', 'max:120'],
        ];
    }
}
