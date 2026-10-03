<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Models\CottageType;

/**
 * Deletes (soft) a cottage type that no cottage uses.
 */
class DeleteCottageType extends Action
{
    /**
     * @throws CannotDelete when cottages still use it
     */
    public function handle(CottageType $cottageType): void
    {
        if ($cottageType->cottages()->exists()) {
            throw CannotDelete::because(__('Cottages still use the type ":name". Change or delete them first, or deactivate the type.', ['name' => $cottageType->name]));
        }

        $cottageType->delete();
    }
}
