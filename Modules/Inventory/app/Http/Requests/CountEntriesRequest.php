<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CountEntriesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.count.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['entries' => ['required', 'array', 'max:2000'], 'entries.*' => ['nullable', 'numeric', 'min:0', 'max:99999999999']];
    }

    /**
     * @return array<int, string|null>
     */
    public function entries(): array
    {
        $entries = [];

        foreach ((array) $this->validated('entries') as $itemId => $value) {
            $entries[(int) $itemId] = $value === null || $value === '' ? null : (string) $value;
        }

        return $entries;
    }
}
