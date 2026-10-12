<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.recipe.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'yield_quantity' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            'yield_unit' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:300'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['nullable', 'integer', TenantRule::exists('items')],
            'lines.*.sub_recipe_id' => ['nullable', 'integer', TenantRule::exists('recipes')],
            'lines.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'lines.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'lt:100'],
        ];
    }

    /**
     * @return list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string|null}>
     */
    public function lines(): array
    {
        $lines = [];

        foreach ((array) $this->validated('lines') as $row) {
            if (($row['item_id'] ?? '') === '' && ($row['sub_recipe_id'] ?? '') === '') {
                continue;
            }

            $lines[] = [
                'item_id' => ($row['item_id'] ?? '') !== '' ? (int) $row['item_id'] : null, 'sub_recipe_id' => ($row['sub_recipe_id'] ?? '') !== '' ? (int) $row['sub_recipe_id'] : null,
                'quantity' => (string) ($row['quantity'] ?? ''), 'waste_percent' => ($row['waste_percent'] ?? '') !== '' ? (string) $row['waste_percent'] : null,
            ];
        }

        return $lines;
    }
}
