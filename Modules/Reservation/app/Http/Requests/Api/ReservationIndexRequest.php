<?php

namespace Modules\Reservation\Http\Requests\Api;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Enums\ReservationStatus;

/**
 * `GET /api/v1/reservation/reservations` (Step 8.1): filters and paging. Permission: `api.can:reservation.booking.view`.
 */
class ReservationIndexRequest extends FormRequest
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
        return [
            /** Only this property (default: every property the token may use). */
            'property_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(ReservationStatus::class)],
            'source' => ['nullable', Rule::enum(ReservationSource::class)],
            'guest_id' => ['nullable', 'integer', TenantRule::exists('guests')],
            /** Arrivals on or after this date, `Y-m-d`. */
            'arrival_from' => ['nullable', 'date_format:Y-m-d'],
            /** Arrivals on or before this date, `Y-m-d`. */
            'arrival_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:arrival_from'],
            /** Changed at or after this time (ISO 8601), for syncing. */
            'updated_since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
