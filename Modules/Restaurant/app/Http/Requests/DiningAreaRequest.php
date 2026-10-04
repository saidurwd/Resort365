<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * A dining area of the outlet: name unique in the outlet.
 */
class DiningAreaRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('editFloorPlan', $this->outlet()) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', TenantRule::unique('dining_areas', 'name')->where('outlet_id', $this->outlet()->id)->ignore($this->route('area'))],
        ];
    }
}
