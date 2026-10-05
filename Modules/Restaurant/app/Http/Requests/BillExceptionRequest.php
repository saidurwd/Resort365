<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\CompReason;

/**
 * A manager-controlled step on a bill (JSON): reopen (order), complimentary (reason, note) or void of a
 * settled bill (reason, food prepared), each with a manager's approval when the person lacks the permission.
 */
class BillExceptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can($this->routeIs('pos.orders.reopen') ? 'restaurant.order.take' : 'restaurant.bill.settle') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'comp_reason' => [Rule::requiredIf($this->routeIs('pos.bills.comp')), 'nullable', Rule::enum(CompReason::class)],
            'note' => ['nullable', 'string', 'max:300'],
            'reason' => [Rule::requiredIf($this->routeIs('pos.bills.void')), 'nullable', 'string', 'max:300'],
            'food_prepared' => ['nullable', 'boolean'],
            'approval_id' => ['nullable', 'integer'],
        ];
    }
}
