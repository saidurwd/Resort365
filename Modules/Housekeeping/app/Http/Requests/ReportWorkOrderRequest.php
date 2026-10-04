<?php

namespace Modules\Housekeeping\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Housekeeping\Models\MaintenanceRequest;

/**
 * Reporting a fault: a room of the current property or a place, what is wrong and how urgent.
 */
class ReportWorkOrderRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceRequest::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'room_id' => ['nullable', 'integer', $this->roomRule()],
            'location' => ['nullable', 'required_without:room_id', 'string', 'max:150'],
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(WorkOrderCategory::class)],
            'priority' => ['required', Rule::enum(WorkOrderPriority::class)],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
