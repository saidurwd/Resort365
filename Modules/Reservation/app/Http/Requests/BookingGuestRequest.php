<?php

namespace Modules\Reservation\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reservation\Enums\ReservationSource;

/**
 * Booking wizard step 3: an existing guest, or a new one (first name and contact details).
 */
class BookingGuestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reservation.booking.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'guest_mode' => ['required', 'in:existing,new'],
            'guest_id' => ['required_if:guest_mode,existing', 'nullable', 'integer', TenantRule::exists('guests')->withoutTrashed()],
            'first_name' => ['required_if:guest_mode,new', 'nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^\+?[\d\s\-()]{6,}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'confirm_new' => ['boolean'],
            'company_id' => ['nullable', 'integer', TenantRule::exists('companies')->withoutTrashed()],
            'travel_agent_id' => ['nullable', 'integer', TenantRule::exists('travel_agents')->withoutTrashed()],
            'source' => ['required', Rule::enum(ReservationSource::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['confirm_new' => $this->boolean('confirm_new')]);
    }
}
