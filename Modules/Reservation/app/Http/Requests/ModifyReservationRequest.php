<?php

namespace Modules\Reservation\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Property\DTOs\CottageSummary;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\DTOs\BookingItem;
use Modules\Reservation\DTOs\ReservationChange;
use Modules\Reservation\Enums\ItemType;
use Modules\Reservation\Models\Reservation;

/**
 * A reservation's new stay: dates and items. Each item is a unit ("room:{id}" or
 * "cottage:{id}") of the reservation's property, with a rate plan and occupancy; rows marked
 * remove (or without a unit) are left out.
 */
class ModifyReservationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->reservation()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $checkIn = $this->input('check_in');
        $latest = is_string($checkIn) && strtotime($checkIn) !== false ? CarbonImmutable::parse($checkIn)->addDays(SearchAvailabilityRequest::MAX_NIGHTS)->toDateString() : '2999-12-31';

        return [
            'check_in' => ['required', 'date_format:Y-m-d'],
            'check_out' => ['required', 'date_format:Y-m-d', 'after:check_in', 'before_or_equal:'.$latest],
            'items' => ['required', 'array', 'max:30'],
            'items.*.unit' => ['nullable', 'string', 'regex:/^(room|cottage):\d+$/'],
            'items.*.rate_plan' => ['required_with:items.*.unit', 'nullable', 'integer', TenantRule::exists('rate_plans')->where('property_id', $this->reservation()->property_id)->withoutTrashed()],
            'items.*.adults' => ['required_with:items.*.unit', 'nullable', 'integer', 'min:1', 'max:50'],
            'items.*.children' => ['nullable', 'integer', 'min:0', 'max:30'],
            'items.*.remove' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $catalog = app(InventoryCatalog::class);
            $propertyId = $this->reservation()->property_id;
            $units = [
                ...array_map(fn (RoomSummary $room): string => 'room:'.$room->id, $catalog->rooms($propertyId)),
                ...array_map(fn (CottageSummary $cottage): string => 'cottage:'.$cottage->id, $catalog->cottages($propertyId)),
            ];
            $chosen = array_map(fn (BookingItem $item): string => $item->type->value.':'.$item->unitId, $this->items());

            if ($chosen === []) {
                $validator->errors()->add('items', __('Keep at least one room or cottage.'));
            } elseif (array_diff($chosen, $units) !== []) {
                $validator->errors()->add('items', __('Choose rooms and cottages of this property.'));
            } elseif (count($chosen) !== count(array_unique($chosen))) {
                $validator->errors()->add('items', __('Each room or cottage can be booked once.'));
            }
        }];
    }

    public function change(): ReservationChange
    {
        return new ReservationChange(
            CarbonImmutable::parse((string) $this->validated('check_in')),
            CarbonImmutable::parse((string) $this->validated('check_out')),
            $this->items(),
        );
    }

    /**
     * @return list<BookingItem>
     */
    private function items(): array
    {
        $items = [];

        foreach ((array) $this->input('items', []) as $row) {
            if (! is_array($row) || ! is_string($row['unit'] ?? null) || $row['unit'] === '' || filter_var($row['remove'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                continue;
            }

            [$type, $id] = explode(':', $row['unit']);
            $items[] = new BookingItem(ItemType::from($type), (int) $id, (int) $row['rate_plan'], (int) $row['adults'], (int) ($row['children'] ?? 0));
        }

        return $items;
    }

    private function reservation(): Reservation
    {
        $reservation = $this->route('reservation');

        return $reservation instanceof Reservation ? $reservation : abort(404);
    }
}
