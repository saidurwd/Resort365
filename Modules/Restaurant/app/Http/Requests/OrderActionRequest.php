<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\Course;

/**
 * Send (or fire a held course), transfer to a table, merge another order in, cancel (JSON).
 */
class OrderActionRequest extends FormRequest
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
            'course' => ['nullable', Rule::enum(Course::class)],
            'table_id' => [Rule::requiredIf($this->routeIs('pos.orders.transfer')), 'nullable', 'integer', TenantRule::exists('dining_tables')],
            'order_id' => [Rule::requiredIf($this->routeIs('pos.orders.merge')), 'nullable', 'integer', TenantRule::exists('pos_orders')],
        ];
    }
}
