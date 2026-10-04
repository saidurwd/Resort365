<?php

namespace Modules\Housekeeping\Http\Requests;

use App\Support\Tenancy\TenantRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Housekeeping\Enums\WorkOrderPriority;
use Modules\Housekeeping\Enums\WorkOrderStatus;
use Modules\Housekeeping\Models\MaintenanceRequest;

/**
 * Working a maintenance order: status, priority, technician, costs and what was done.
 */
class UpdateWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($order = $this->route('order')) instanceof MaintenanceRequest && ($this->user()?->can('update', $order) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(WorkOrderStatus::class)],
            'priority' => ['required', Rule::enum(WorkOrderPriority::class)],
            'assigned_to' => ['nullable', 'integer', TenantRule::exists('users')],
            'labour_cost' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'parts_cost' => ['required', 'decimal:0,2', 'min:0', 'max:9999999999'],
            'resolution' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
