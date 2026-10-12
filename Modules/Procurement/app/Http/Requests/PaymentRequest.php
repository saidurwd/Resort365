<?php

namespace Modules\Procurement\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.payment.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'integer', TenantRule::exists('vendors')],
            'method' => ['required', 'in:cash,bank_transfer,mobile_wallet,cheque'],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999999'],
            'paid_on' => ['nullable', 'date_format:Y-m-d'],
            'reference' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string', 'max:300'],
            'allocations' => ['nullable', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allocations(): array
    {
        $rows = [];

        foreach ((array) $this->validated('allocations', []) as $billId => $amount) {
            if ($amount !== null && $amount !== '') {
                $rows[(int) $billId] = (string) $amount;
            }
        }

        return $rows;
    }
}
