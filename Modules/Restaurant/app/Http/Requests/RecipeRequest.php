<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecipeRequest extends FormRequest
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
            'sections' => ['required', 'array'],
            'sections.*' => ['nullable', 'array'],
            'sections.*.*.item_id' => ['nullable', 'integer', TenantRule::exists('items')],
            'sections.*.*.sub_recipe_id' => ['nullable', 'integer', TenantRule::exists('recipes')],
            'sections.*.*.quantity' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'sections.*.*.waste_percent' => ['nullable', 'numeric', 'min:0', 'lt:100'],
        ];
    }

    /**
     * Rows with neither an ingredient nor a sub-recipe are dropped.
     *
     * @return array<string, list<array{item_id: int|null, sub_recipe_id: int|null, quantity: string, waste_percent: string|null}>>
     */
    public function sections(): array
    {
        $sections = [];

        foreach ((array) $this->validated('sections') as $key => $rows) {
            $sections[(string) $key] = [];

            foreach ((array) $rows as $row) {
                if (($row['item_id'] ?? '') === '' && ($row['sub_recipe_id'] ?? '') === '') {
                    continue;
                }

                $sections[(string) $key][] = [
                    'item_id' => ($row['item_id'] ?? '') !== '' ? (int) $row['item_id'] : null, 'sub_recipe_id' => ($row['sub_recipe_id'] ?? '') !== '' ? (int) $row['sub_recipe_id'] : null,
                    'quantity' => (string) ($row['quantity'] ?? ''), 'waste_percent' => ($row['waste_percent'] ?? '') !== '' ? (string) $row['waste_percent'] : null,
                ];
            }
        }

        return $sections;
    }
}
