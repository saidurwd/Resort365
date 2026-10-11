<?php

namespace Modules\Inventory\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Inventory\Enums\StoreKind;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.store.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii'],
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::enum(StoreKind::class)],
            'department_id' => ['nullable', 'integer'],
            'is_active' => ['boolean'],
        ];
    }
}
