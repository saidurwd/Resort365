<?php

namespace Modules\Housekeeping\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Housekeeping\Enums\WorkOrderCategory;
use Modules\Housekeeping\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Housekeeping\Models\MaintenanceSchedule;

/**
 * A preventive maintenance schedule of the current property.
 */
class ScheduleRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceSchedule::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'category' => ['required', Rule::enum(WorkOrderCategory::class)],
            'room_id' => ['nullable', 'integer', $this->roomRule()],
            'location' => ['nullable', 'string', 'max:150'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:3650'],
            'next_due_on' => ['required', 'date_format:Y-m-d'],
            'assigned_to' => ['nullable', 'integer', TenantRule::exists('users')],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
