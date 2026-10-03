<?php

namespace Modules\Property\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Http\Requests\Concerns\CottageRules;
use Modules\Property\Models\Cottage;
use Modules\Property\Models\Room;
use Modules\Property\Services\RoomNumberSequence;

/**
 * The quick "add cottage with N rooms" form.
 */
class CreateCottageRequest extends FormRequest
{
    use CottageRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Cottage::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->cottageRules(null),
            'room_count' => ['required', 'integer', 'min:1', 'max:30'],
            'room_type_id' => ['required', 'integer', TenantRule::exists('room_types')->where('property_id', $this->propertyId(null))->withoutTrashed()],
            'first_room_number' => ['required', 'string', 'max:15', 'alpha_dash:ascii', 'regex:'.RoomNumberSequence::PATTERN],
            'floor' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['first_room_number.regex' => __('The first room number must end in digits, e.g. 101 or A01.')];
    }

    /**
     * The generated room numbers must be free in this property (deleted rooms keep theirs).
     *
     * @return array<int, callable(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $numbers = app(RoomNumberSequence::class)->generate((string) $this->input('first_room_number'), (int) $this->input('room_count'));
            $taken = Room::query()->withTrashed()->where('property_id', $this->propertyId(null))->whereIn('number', $numbers)->pluck('number');

            if ($taken->isNotEmpty()) {
                $validator->errors()->add('first_room_number', __('These room numbers are already used in this property: :numbers.', ['numbers' => $taken->implode(', ')]));
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCottage();
        $this->merge(['first_room_number' => strtoupper(trim((string) $this->input('first_room_number')))]);
    }
}
