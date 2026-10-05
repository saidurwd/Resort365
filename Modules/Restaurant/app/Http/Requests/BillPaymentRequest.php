<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\PaymentMethod;

/**
 * A payment on a bill (JSON): method, amount towards the bill, tip, cash tendered, reference.
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
        ];
    }
}
