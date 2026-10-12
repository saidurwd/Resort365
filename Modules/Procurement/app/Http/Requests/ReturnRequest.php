<?php

namespace Modules\Procurement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.return.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:150'],
            'returned_on' => ['nullable', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return list<array{goods_receipt_line_id: int, quantity: string}>
     */
    public function lines(): array
    {
        $rows = [];

        foreach ((array) $this->validated('lines') as $lineId => $quantity) {
            $rows[] = ['goods_receipt_line_id' => (int) $lineId, 'quantity' => (string) ($quantity ?? '')];
        }

        return $rows;
    }
}
