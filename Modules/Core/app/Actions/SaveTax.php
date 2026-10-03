<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Modules\Core\Models\Tax;

/**
 * Creates or updates a tax (validated by SaveTaxRequest).
 */
class SaveTax extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Tax $tax, array $data): Tax
    {
        $tax ??= new Tax;
        $tax->fill($data)->save();

        return $tax;
    }
}
