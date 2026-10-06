<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Booking or changing a table reservation: date and time in the property's time, party size, table,
 * an in-house guest or an outside customer.
 */
class TableReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.reservation.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'integer', TenantRule::exists('outlets')],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'party_size' => ['required', 'integer', 'min:1', 'max:60'],
            'duration_minutes' => ['nullable', 'integer', 'min:30', 'max:480'],
            'dining_table_id' => ['nullable', 'integer', TenantRule::exists('dining_tables')],
            'reservation_id' => ['nullable', 'integer'],
            'customer_name' => ['nullable', 'string', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'occasion' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
