<?php

namespace Modules\Procurement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AllocateRequest extends FormRequest
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
            'allocations' => ['required', 'array'],
            'allocations.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function allocations(): array
    {
        $rows = [];

        foreach ((array) $this->validated('allocations') as $billId => $amount) {
            if ($amount !== null && $amount !== '') {
                $rows[(int) $billId] = (string) $amount;
            }
        }

        return $rows;
    }
}
