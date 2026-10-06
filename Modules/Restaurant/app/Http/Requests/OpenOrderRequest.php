<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\OrderType;

/**
 * Starting an order on the POS floor: a table and its covers, takeaway, room service to an in-house guest,
 * a delivery to a place, or a staff meal for a named person.
 */
class OpenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return ($user?->can('restaurant.order.take') ?? false) && ($this->input('type') !== OrderType::StaffMeal->value || $user->can('restaurant.order.staff-meal'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(OrderType::class)],
            'table_id' => ['nullable', 'required_if:type,'.OrderType::DineIn->value, 'integer', TenantRule::exists('dining_tables')],
            'covers' => ['nullable', 'integer', 'min:1', 'max:50'],
            'reservation_id' => ['nullable', 'required_if:type,'.OrderType::RoomService->value, 'integer'],
            'location' => ['nullable', 'required_if:type,'.OrderType::LocationDelivery->value, 'string', 'max:190'],
            'name' => ['nullable', 'required_if:type,'.OrderType::StaffMeal->value, 'string', 'max:190'],
        ];
    }
}
