<?php

namespace Modules\Core\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The "try it" box on the taxes screen.
 */
class TryTaxesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('core.tax.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'decimal:0,2', 'min:0', 'max:9999999999999'],
            'category' => ['nullable', 'integer', TenantRule::exists('tax_categories')],
            'inclusive' => ['boolean'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['inclusive' => $this->boolean('inclusive')]);
    }
}
