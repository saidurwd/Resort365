<?php

namespace Modules\Housekeeping\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Housekeeping\Http\Requests\Concerns\ForCurrentProperty;
use Modules\Housekeeping\Models\HousekeepingTask;

/**
 * Giving cleaning tasks to an attendant (or taking them back: no attendant).
 */
class AssignTasksRequest extends FormRequest
{
    use ForCurrentProperty;

    public function authorize(): bool
    {
        return $this->user()?->can('manage', HousekeepingTask::class) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'task_ids' => ['required', 'array', 'min:1'],
            'task_ids.*' => ['integer', TenantRule::exists('housekeeping_tasks')->where('property_id', $this->propertyId())],
            'attendant_id' => ['nullable', 'integer', TenantRule::exists('users')],
        ];
    }
}
