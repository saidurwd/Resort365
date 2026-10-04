<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Models\ExtraService;

class SaveExtraService extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?ExtraService $service, array $data): ExtraService
    {
        $service ??= new ExtraService;
        $service->fill($data)->save();

        return $service;
    }
}
