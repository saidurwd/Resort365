<?php

namespace Modules\Housekeeping\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IssueAmenitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('housekeeping.task.perform') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }

    /**
     * @return list<array{item_id: int, quantity: string}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ((array) $this->validated('items') as $itemId => $quantity) {
            if ($quantity !== null && $quantity !== '' && (float) $quantity > 0) {
                $lines[] = ['item_id' => (int) $itemId, 'quantity' => (string) $quantity];
            }
        }

        return $lines;
    }
}
