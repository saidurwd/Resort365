<?php

namespace Modules\Reservation\Http\Requests\Api;

use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Reservation\DTOs\AvailabilitySearch;
use Modules\Reservation\DTOs\Occupancy;

/**
 * `GET /api/v1/reservation/availability` (Step 8.1): a property, dates, the party and optionally one rate plan and a promotion code.
 * Permission: route middleware `api.can:reservation.availability.view`.
 */
class AvailabilityRequest extends FormRequest
{
    public const int MAX_NIGHTS = 60;

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
            /** The property (one the token may use; see `GET /api/v1/iam/me`). */
            'property_id' => ['required', 'integer'],
            /** Arrival date, `Y-m-d`. */
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            /** Departure date, `Y-m-d`; at most 60 nights after arrival. */
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.self::latestCheckOut($this->input('check_in'))],
            'adults' => ['required', 'integer', 'min:1', 'max:50'],
            'children' => ['nullable', 'integer', 'min:0', 'max:30'],
            /** One rate plan; without it every plan sold at the front desk is searched. */
            'rate_plan_id' => ['nullable', 'integer', TenantRule::exists('rate_plans')->where('property_id', $this->integer('property_id'))->withoutTrashed()],
            'promo_code' => ['nullable', 'string', 'max:30'],
        ];
    }

    public function searchFor(int $ratePlanId): AvailabilitySearch
    {
        $promo = trim((string) $this->input('promo_code'));

        return new AvailabilitySearch(
            $this->integer('property_id'), $ratePlanId,
            CarbonImmutable::parse((string) $this->input('check_in')), CarbonImmutable::parse((string) $this->input('check_out')),
            new Occupancy($this->integer('adults'), $this->integer('children')),
            $promo === '' ? null : $promo,
        );
    }

    public static function latestCheckOut(mixed $checkIn): string
    {
        return is_string($checkIn) && strtotime($checkIn) !== false ? CarbonImmutable::parse($checkIn)->addDays(self::MAX_NIGHTS)->toDateString() : '2999-12-31';
    }
}
