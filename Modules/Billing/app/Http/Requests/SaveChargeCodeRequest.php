<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Models\ChargeCode;

class SaveChargeCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.charge-code.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code')))]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $code = $this->route('charge_code');

        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9_-]+$/', TenantRule::unique('charge_codes', 'code')->ignore($code instanceof ChargeCode ? $code->id : null)],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::enum(ChargeCategory::class)],
            'tax_category_id' => ['nullable', 'integer', TenantRule::exists('tax_categories')],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }
}
