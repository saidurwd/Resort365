<?php

namespace Modules\Reservation\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\DTOs\RoomSummary;
use Modules\Reservation\DTOs\AvailabilityResult;
use Modules\Reservation\Http\Requests\Concerns\CurrentProperty;
use Modules\Reservation\Support\BookingWizard;

/**
 * Booking wizard step 2: whole cottages and rooms, which must still be free and bookable
 * (checked against the availability of the wizard's dates).
 */
class BookingChoiceRequest extends FormRequest
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
        return [
            'cottages' => ['array', 'required_without:rooms'],
            'cottages.*' => ['integer'],
            'rooms' => ['array', 'required_without:cottages'],
            'rooms.*' => ['integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['cottages.required_without' => __('Choose at least one cottage or room.'), 'rooms.required_without' => __('Choose at least one cottage or room.')];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $availability = app(BookingWizard::class)->availability($this->propertyId());

            if ($validator->errors()->isNotEmpty() || ! $availability instanceof AvailabilityResult) {
                return;
            }

            $cottages = [];
            $rooms = [];

            foreach ($availability->cottages as $option) {
                if ($option->isBookable()) {
                    $cottages[] = $option->cottage->id;
                }
            }

            foreach ($availability->roomTypes as $option) {
                if ($option->isBookable()) {
                    array_push($rooms, ...array_map(fn (RoomSummary $room): int => $room->id, $option->rooms));
                }
            }

            if (array_diff(array_map(intval(...), (array) $this->input('cottages', [])), $cottages) !== []
                || array_diff(array_map(intval(...), (array) $this->input('rooms', [])), $rooms) !== []) {
                $validator->errors()->add('cottages', __('Something you chose is no longer free. Please choose again.'));
            }
        }];
    }
}
