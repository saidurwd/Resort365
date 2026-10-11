<?php

namespace Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.item.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_category_id' => ['required', 'integer', TenantRule::exists('item_categories')],
            'sku' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'base_unit_id' => ['required', 'integer', TenantRule::exists('units')],
            'purchase_unit_id' => ['nullable', 'integer', TenantRule::exists('units')],
            'issue_unit_id' => ['nullable', 'integer', TenantRule::exists('units')],
            'is_stockable' => ['boolean'],
            'track_batches' => ['boolean'],
            'track_expiry' => ['boolean'],
            'reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'reorder_qty' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'standard_cost' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'conversions' => ['nullable', 'array', 'max:10'],
            'conversions.*.unit_id' => ['required', 'integer', TenantRule::exists('units')],
            'conversions.*.factor' => ['required', 'numeric', 'gt:0', 'max:999999999999'],
            'levels' => ['nullable', 'array', 'max:50'],
            'levels.*.store_id' => ['required', 'integer', TenantRule::exists('stores')],
            'levels.*.par_level' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'levels.*.reorder_level' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function item(): array
    {
        $data = $this->safe()->except(['conversions', 'levels']);

        foreach (['reorder_level', 'reorder_qty'] as $field) {
            $data[$field] = (string) ($data[$field] ?? '0') === '' ? '0' : (string) ($data[$field] ?? '0');
        }

        foreach (['is_stockable', 'track_batches', 'track_expiry', 'is_active'] as $field) {
            $data[$field] = $this->boolean($field);
        }

        return $data;
    }

    /**
     * @return list<array{unit_id: int, factor: string}>
     */
    public function conversions(): array
    {
        return array_values(array_map(fn (array $row): array => ['unit_id' => (int) $row['unit_id'], 'factor' => (string) $row['factor']], (array) $this->validated('conversions', [])));
    }

    /**
     * @return list<array{store_id: int, par_level: string|null, reorder_level: string|null}>
     */
    public function levels(): array
    {
        return array_values(array_map(fn (array $row): array => [
            'store_id' => (int) $row['store_id'], 'par_level' => ($row['par_level'] ?? '') !== '' ? (string) $row['par_level'] : null, 'reorder_level' => ($row['reorder_level'] ?? '') !== '' ? (string) $row['reorder_level'] : null,
        ], (array) $this->validated('levels', [])));
    }
}
