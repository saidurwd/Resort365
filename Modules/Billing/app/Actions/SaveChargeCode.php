<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Models\ChargeCode;

class SaveChargeCode extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?ChargeCode $code, array $data): ChargeCode
    {
        $code ??= new ChargeCode;
        $code->fill($data)->save();

        return $code;
    }
}
