<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\OrderType;

/**
 * Starting an order on the POS floor: a table and its covers, or takeaway.
 */
class OpenOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.order.take') ?? false;
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
        ];
    }
}
