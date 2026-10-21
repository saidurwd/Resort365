<?php

namespace Modules\Platform\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Platform\Services\ModuleCatalog;

class PlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('platform') !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:2', 'max:30'],
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'yearly_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'currency_code' => ['required', 'string', 'size:3'],
            'max_properties' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_rooms' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'storage_gb' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(ModuleCatalog::aliases())],
            'api_access' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }
}
