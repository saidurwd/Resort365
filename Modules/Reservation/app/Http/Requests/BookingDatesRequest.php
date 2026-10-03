<?php

namespace Modules\Reservation\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservation\Http\Requests\Concerns\CurrentProperty;

/**
 * Booking wizard step 1: dates, party and rate plan.
 */
class BookingDatesRequest extends FormRequest
{
    use CurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('reservation.booking.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $checkIn = $this->input('check_in');
        $latest = is_string($checkIn) && strtotime($checkIn) !== false ? CarbonImmutable::parse($checkIn)->addDays(SearchAvailabilityRequest::MAX_NIGHTS)->toDateString() : '2999-12-31';

        return [
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->subDay()->toDateString()],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.$latest],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'rate_plan' => ['required', 'integer', TenantRule::exists('rate_plans')->where('property_id', $this->propertyId())->where('is_active', true)->withoutTrashed()],
        ];
    }
}
