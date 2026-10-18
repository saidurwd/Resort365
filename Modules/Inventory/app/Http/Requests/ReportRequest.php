<?php

namespace Modules\Inventory\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.report.view') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'as_of' => ['nullable', 'date_format:Y-m-d'],
            'store' => ['nullable', 'integer', TenantRule::exists('stores')],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'export' => ['nullable', 'in:csv'],
        ];
    }
}
