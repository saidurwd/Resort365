<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Exceptions\CannotDelete;
use Modules\Property\Models\Cottage;

/**
 * Deletes (soft) a cottage that has no rooms left.
 */
class DeleteCottage extends Action
{
    /**
     * @throws CannotDelete when it still has rooms
     */
    public function handle(Cottage $cottage): void
    {
        if ($cottage->rooms()->exists()) {
            throw CannotDelete::because(__('Cottage ":name" still has rooms. Delete or move them first, or deactivate the cottage.', ['name' => $cottage->name]));
        }

        $cottage->delete();
    }
}
