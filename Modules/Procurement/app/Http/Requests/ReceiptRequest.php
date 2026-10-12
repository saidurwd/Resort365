<?php

namespace Modules\Procurement\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('procurement.receipt.create') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'received_on' => ['nullable', 'date_format:Y-m-d'],
            'delivery_note' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.accepted_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.rejected_quantity' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'lines.*.reject_reason' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return list<array{purchase_order_line_id: int, accepted_quantity: string, rejected_quantity: string, reject_reason: string|null}>
     */
    public function lines(): array
    {
        $rows = [];

        foreach ((array) $this->validated('lines') as $lineId => $line) {
            $rows[] = ['purchase_order_line_id' => (int) $lineId, 'accepted_quantity' => (string) ($line['accepted_quantity'] ?? ''), 'rejected_quantity' => (string) ($line['rejected_quantity'] ?? ''), 'reject_reason' => ($line['reject_reason'] ?? null) ?: null];
        }

        return $rows;
    }
}
