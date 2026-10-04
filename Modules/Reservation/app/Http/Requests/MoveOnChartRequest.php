<?php

namespace Modules\Reservation\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A booking dragged on the tape chart: which room item and the room it was dropped on (JSON).
 */
class MoveOnChartRequest extends FormRequest
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
        return [
            'item_id' => ['required', 'integer', TenantRule::exists('reservation_items')],
            'room_id' => ['required', 'integer', TenantRule::exists('rooms')->withoutTrashed()],
        ];
    }
}
