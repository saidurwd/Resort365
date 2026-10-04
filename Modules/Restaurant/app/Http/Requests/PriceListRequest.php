<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * An outlet's whole price list, sent as JSON in one field (rows) so a long menu fits in one request.
 */
class PriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('restaurant.price.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $rows = json_decode((string) $this->input('rows_json', ''), true);

        $this->merge(['rows' => is_array($rows) ? $rows : null]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'rows' => ['required', 'array', 'max:5000'],
            'rows.*.item_id' => ['required', 'integer'],
            'rows.*.variant_id' => ['nullable', 'integer'],
            'rows.*.on_sale' => ['required', 'boolean'],
            'rows.*.price' => ['nullable', 'required_if_accepted:rows.*.on_sale', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'rows.*.station_id' => ['nullable', 'integer'],
            'rows.*.is_available' => ['boolean'],
            'rows.*.is_package_eligible' => ['boolean'],
            'rows.*.schedule_ids' => ['nullable', 'array'],
            'rows.*.schedule_ids.*' => ['integer'],
        ];
    }
}
