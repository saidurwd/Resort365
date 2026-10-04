<?php

namespace Modules\Billing\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\PaymentMethod;

class LedgerPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.city-ledger.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'max:9999999999'],
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
