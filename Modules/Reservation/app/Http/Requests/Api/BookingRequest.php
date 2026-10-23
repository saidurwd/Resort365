<?php

namespace Modules\Reservation\Http\Requests\Api;

use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Reservation\DTOs\NewReservation;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Enums\ReservationSource;
use Modules\Reservation\Services\BookingItemsResolver;

/**
 * A stay as the API takes it (Step 8.1): property, dates, one rate plan and the rooms or cottages with their party. Shared by
 * quotes and bookings. A room is given by `room_id`, or by `room_type_id` for the first free room of that type.
 */
abstract class BookingRequest extends FormRequest
{
    public const int MAX_ITEMS = 20;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $property = $this->integer('property_id');

        return [
            'property_id' => ['required', 'integer'],
            /** Arrival date, `Y-m-d`. */
            'check_in' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            /** Departure date, `Y-m-d`; at most 60 nights after arrival. */
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.AvailabilityRequest::latestCheckOut($this->input('check_in'))],
            'rate_plan_id' => ['required', 'integer', TenantRule::exists('rate_plans')->where('property_id', $property)->withoutTrashed()],
            /** The rooms and whole cottages, each with its party (1 to 20). */
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.type' => ['required', Rule::enum(ItemType::class)],
            /** A room by id (type `room`). */
            'items.*.room_id' => ['nullable', 'integer'],
            /** Or a room type (type `room`): the first free room of the type is taken. */
            'items.*.room_type_id' => ['nullable', 'integer'],
            /** A whole cottage by id (type `cottage`). */
            'items.*.cottage_id' => ['nullable', 'integer'],
            'items.*.adults' => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'promo_code' => ['nullable', 'string', 'max:30'],
            /** A negotiated deposit percent within the plan's deposit policy (default: the policy's). */
            'deposit_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'company_id' => ['nullable', 'integer', TenantRule::exists('companies')->withoutTrashed()],
            'travel_agent_id' => ['nullable', 'integer', TenantRule::exists('travel_agents')->withoutTrashed()],
            'group_name' => ['nullable', 'string', 'max:150'],
            'special_requests' => ['nullable', 'string', 'max:2000'],
            'internal_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['check_out.before_or_equal' => __('A stay covers at most :count nights.', ['count' => AvailabilityRequest::MAX_NIGHTS])];
    }

    /**
     * The booking as CreateReservation and SaveQuote take it, with each item checked against availability.
     */
    public function reservation(int $guestId, ?int $userId): NewReservation
    {
        $checkIn = CarbonImmutable::parse((string) $this->input('check_in'));
        $checkOut = CarbonImmutable::parse((string) $this->input('check_out'));
        $text = fn (string $key): ?string => is_string($this->input($key)) && trim($this->input($key)) !== '' ? trim($this->input($key)) : null;
        /** @var list<array{type: string, room_id?: int|null, room_type_id?: int|null, cottage_id?: int|null, adults: int, children?: int|null}> $rows */
        $rows = array_values((array) $this->validated('items'));

        $items = app(BookingItemsResolver::class)->resolve($this->integer('property_id'), $this->integer('rate_plan_id'), $checkIn, $checkOut, $rows, $text('promo_code'));

        return new NewReservation(
            $this->integer('property_id'), $checkIn, $checkOut, $items, $guestId, ReservationSource::Api,
            $this->filled('company_id') ? $this->integer('company_id') : null,
            $this->filled('travel_agent_id') ? $this->integer('travel_agent_id') : null,
            $this->filled('deposit_percent') ? (string) $this->input('deposit_percent') : null,
            $text('promo_code'), $text('special_requests'), $text('internal_notes'), $userId, false, $text('group_name'),
        );
    }

    /**
     * Each item names exactly one unit: a room (`room_id` or `room_type_id`) or a cottage (`cottage_id`).
     *
     * @return array<int, callable(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            foreach ((array) $this->input('items', []) as $index => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $room = filled($item['room_id'] ?? null);
                $roomType = filled($item['room_type_id'] ?? null);
                $cottage = filled($item['cottage_id'] ?? null);
                $valid = match ($item['type'] ?? null) {
                    'room' => ($room xor $roomType) && ! $cottage,
                    'cottage' => $cottage && ! $room && ! $roomType,
                    default => true,
                };

                if (! $valid) {
                    $validator->errors()->add("items.{$index}", __('Give a room with room_id or room_type_id, or a cottage with cottage_id.'));
                }
            }
        }];
    }
}
