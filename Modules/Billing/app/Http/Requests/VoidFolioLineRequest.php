<?php

namespace Modules\Billing\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VoidFolioLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('billing.folio.void') ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'folio_line_id' => ['required', 'integer', TenantRule::exists('folio_lines')],
            'reason' => ['required', 'string', 'max:190'],
        ];
    }
}
