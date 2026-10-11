<?php

namespace Modules\Inventory\Actions;

use App\Support\Actions\Action;
use Modules\Inventory\Enums\StoreKind;
use Modules\Inventory\Exceptions\InventoryRuleViolated;
use Modules\Inventory\Models\Store;
use Modules\Property\Contracts\DepartmentDirectory;
use Modules\Property\DTOs\DepartmentSummary;

/**
 * Saves a store of a property (Step 5.2), tied to one of the tenant's departments.
 */
class SaveStore extends Action
{
    public function __construct(private readonly DepartmentDirectory $departments) {}

    /**
     * @param  array{code: string, name: string, kind: string, department_id?: int|null, is_active?: bool}  $data
     *
     * @throws InventoryRuleViolated
     */
    public function handle(int $propertyId, ?Store $store, array $data): Store
    {
        $departmentId = $data['department_id'] ?? null;

        if ($departmentId !== null && ! $this->departments->find($departmentId) instanceof DepartmentSummary) {
            throw new InventoryRuleViolated(__('Choose one of the tenant\'s departments.'));
        }

        $code = strtoupper(trim($data['code']));

        if (Store::query()->where('property_id', $propertyId)->where('code', $code)->when($store instanceof Store, fn ($query) => $query->whereKeyNot($store?->id))->exists()) {
            throw new InventoryRuleViolated(__('This property already has a store with the code :code.', ['code' => $code]));
        }

        $store ??= new Store(['property_id' => $propertyId]);
        $store->fill(['code' => $code, 'name' => trim($data['name']), 'kind' => StoreKind::from($data['kind']), 'department_id' => $departmentId, 'is_active' => $data['is_active'] ?? true])->save();

        return $store;
    }
}
