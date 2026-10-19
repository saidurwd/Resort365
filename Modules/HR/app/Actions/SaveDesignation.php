<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Designation;

/**
 * Creates or changes a designation (Step 6.1); names are unique per tenant.
 */
class SaveDesignation extends Action
{
    /**
     * @param  array<string, mixed>  $data  name, department_id, is_active
     *
     * @throws HrRuleViolated
     */
    public function handle(?Designation $designation, array $data): Designation
    {
        $name = trim((string) $data['name']);

        if (Designation::query()->where('name', $name)->when($designation instanceof Designation, fn ($query) => $query->whereKeyNot($designation?->id))->exists()) {
            throw new HrRuleViolated(__('There is already a designation called :name.', ['name' => $name]));
        }

        $designation ??= new Designation;
        $designation->fill([
            'name' => $name, 'department_id' => isset($data['department_id']) && $data['department_id'] !== '' ? (int) $data['department_id'] : null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        return $designation;
    }
}
