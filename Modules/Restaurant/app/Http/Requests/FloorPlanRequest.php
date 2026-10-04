<?php

namespace Modules\Restaurant\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Restaurant\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Restaurant\Services\FloorPlanGeometry;

/**
 * Table positions dragged on a dining area's floor plan (JSON).
 */
class FloorPlanRequest extends FormRequest
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
            'positions' => ['required', 'array', 'min:1', 'max:500'],
            'positions.*.id' => ['required', 'integer', 'distinct'],
            'positions.*.x' => ['required', 'numeric', 'min:0', 'max:'.FloorPlanGeometry::WIDTH],
            'positions.*.y' => ['required', 'numeric', 'min:0', 'max:'.FloorPlanGeometry::HEIGHT],
        ];
    }
}
