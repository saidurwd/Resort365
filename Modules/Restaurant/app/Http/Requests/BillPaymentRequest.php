<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\PaymentMethod;

/**
 * A payment on a bill (JSON): method, amount towards the bill, tip, cash tendered, reference; for a
 * room charge the booking (and the guest's signature), for the city ledger the company.
 */
class BillPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.bill.settle') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999'],
            'tip' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'tendered' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'reference' => ['nullable', 'string', 'max:100'],
            'reservation_id' => ['nullable', 'required_if:method,room_charge', 'integer'],
            'company_id' => ['nullable', 'required_if:method,city_ledger', 'integer'],
            'signature' => ['nullable', 'string', 'max:600000'],
        ];
    }
}
