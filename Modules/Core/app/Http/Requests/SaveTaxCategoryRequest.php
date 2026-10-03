<?php

namespace Modules\Core\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Core\Models\TaxCategory;

class SaveTaxCategoryRequest extends FormRequest
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
        $category = $this->route('tax_category');

        return [
            'code' => ['required', 'string', 'max:20', 'alpha_dash:ascii', TenantRule::unique('tax_categories', 'code')->ignore($category instanceof TaxCategory ? $category->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'tax_ids' => ['array'],
            'tax_ids.*' => ['integer', TenantRule::exists('taxes')],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => strtoupper(trim((string) $this->input('code'))), 'is_active' => ! $this->has('is_active') || $this->boolean('is_active')]);
    }
}
