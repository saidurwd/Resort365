<?php

namespace Modules\Core\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Core\Enums\TaxType;
use Modules\Core\Models\Tax;

class SaveTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.tax.manage') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $tax = $this->route('tax');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', TenantRule::unique('taxes', 'code')->ignore($tax instanceof Tax ? $tax->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(TaxType::class)],
            'rate' => ['required', 'decimal:0,4', 'min:0', $this->input('type') === TaxType::Percent->value ? 'max:100' : 'max:99999999999'],
            'is_compound' => ['boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'sort_order' => (int) $this->input('sort_order'),
            'is_compound' => $this->boolean('is_compound'),
            'is_active' => ! $this->has('is_active') || $this->boolean('is_active'),
        ]);
    }
}
