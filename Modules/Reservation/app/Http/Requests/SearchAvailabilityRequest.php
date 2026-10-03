<?php

namespace Modules\Reservation\Http\Requests;

use App\Support\Tenancy\PropertyContext;
use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\Occupancy;

/**
 * Reservations → Availability: dates, party, rate plan and promo code (GET).
 */
class SearchAvailabilityRequest extends FormRequest
{
    public const int MAX_NIGHTS = 60;

    public function authorize(): bool
    {
        return $this->user()?->can('reservation.availability.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.$this->latestCheckOut()],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'rate_plan' => ['required', 'integer', TenantRule::exists('rate_plans')->where('property_id', $this->propertyId())->withoutTrashed()],
            'promo_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['check_out.before_or_equal' => __('A search covers at most :count nights.', ['count' => self::MAX_NIGHTS])];
    }

    public function search(): AvailabilitySearch
    {
        $promo = trim((string) $this->input('promo_code'));

        return new AvailabilitySearch(
            $this->propertyId(),
            $this->integer('rate_plan'),
            CarbonImmutable::parse((string) $this->input('check_in')),
            CarbonImmutable::parse((string) $this->input('check_out')),
            new Occupancy($this->integer('adults'), $this->integer('children')),
            $promo === '' ? null : $promo,
        );
    }

    public function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }

    private function latestCheckOut(): string
    {
        $checkIn = $this->input('check_in');

        return is_string($checkIn) && strtotime($checkIn) !== false ? CarbonImmutable::parse($checkIn)->addDays(self::MAX_NIGHTS)->toDateString() : '2999-12-31';
    }
}
