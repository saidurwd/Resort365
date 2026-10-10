<?php

namespace Modules\Accounting\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Foundation\Http\FormRequest;

class AccountMappingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('accounting.account.manage') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mappings' => ['required', 'array', 'max:200'],
            'mappings.*' => ['nullable', 'integer', TenantRule::exists('accounts')],
        ];
    }

    /**
     * @return array<string, int|null>
     */
    public function mappings(): array
    {
        return array_map(fn ($id): ?int => $id === null || $id === '' ? null : (int) $id, (array) $this->validated('mappings'));
    }
}
