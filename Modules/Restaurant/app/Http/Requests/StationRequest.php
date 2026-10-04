<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\StationOutput;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * A kitchen station of the outlet: name unique in the outlet, output and (for printed tickets) a printer of the property.
 */
class StationRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:100', TenantRule::unique('kitchen_stations', 'name')->where('outlet_id', $this->outlet()->id)->ignore($this->route('station'))],
            'output' => ['required', Rule::enum(StationOutput::class)],
            'printer_id' => ['nullable', 'integer', TenantRule::exists('printers')->where('property_id', $this->propertyId())],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
