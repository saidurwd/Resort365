<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * A POS device of the outlet: its name and receipt printer.
 */
class TerminalRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->outlet()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'receipt_printer_id' => ['nullable', 'integer', TenantRule::exists('printers')->where('property_id', $this->propertyId())->where('type', 'receipt')],
            'is_active' => ['boolean'],
        ];
    }
}
