<?php

namespace Modules\Restaurant\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;

/**
 * A table of the outlet: number unique in the outlet, seats, shape and one of the outlet's areas.
 */
class DiningTableRequest extends FormRequest
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
            'dining_area_id' => ['required', 'integer', TenantRule::exists('dining_areas')->where('outlet_id', $this->outlet()->id)],
            'number' => ['required', 'alpha_dash', 'max:10', TenantRule::unique('dining_tables', 'number')->where('outlet_id', $this->outlet()->id)->ignore($this->route('table'))],
            'seats' => ['required', 'integer', 'min:1', 'max:30'],
            'shape' => ['required', Rule::enum(TableShape::class)],
            'is_active' => ['boolean'],
        ];
    }
}
