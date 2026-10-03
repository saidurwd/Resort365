<?php

namespace Modules\Property\Actions;

use App\Support\Actions\Action;
use Modules\Property\Models\Cottage;

/**
 * Changes a cottage's details (validated by UpdateCottageRequest). Rooms are managed separately.
 */
class UpdateCottage extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Cottage $cottage, array $data): Cottage
    {
        $cottage->fill($data)->save();

        return $cottage;
    }
}
