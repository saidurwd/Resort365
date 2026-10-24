<?php

namespace Modules\Website\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Property\Contracts\UnitShowcases;
use Modules\Property\DTOs\UnitShowcase;
use Modules\Website\Models\WebsiteSite;

/**
 * Website → Rooms and cottages (Step 8.2a): per room type or cottage type (`units[room_type:4][…]`) its texts per language,
 * featured, hidden and order.
 */
class SaveWebsiteUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof WebsiteSite && ($this->user()?->can('update', $site) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $locales = implode(',', array_keys((array) config('website.locales')));

        return [
            'units' => ['required', 'array'],
            'units.*.headline' => ['nullable', 'array:'.$locales],
            'units.*.headline.*' => ['nullable', 'string', 'max:150'],
            'units.*.description' => ['nullable', 'array:'.$locales],
            'units.*.description.*' => ['nullable', 'string', 'max:5000'],
            'units.*.is_featured' => ['boolean'],
            'units.*.is_hidden' => ['boolean'],
            'units.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<int, callable(Validator):void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $site = $this->route('site');
            $known = $site instanceof WebsiteSite ? array_map(fn (UnitShowcase $unit): string => $unit->key(), app(UnitShowcases::class)->forProperty($site->property_id)) : [];

            if (array_diff(array_keys((array) $this->input('units', [])), $known) !== []) {
                $validator->errors()->add('units', __('Unknown room or cottage type.'));
            }
        }];
    }
}
