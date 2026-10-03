<?php

namespace Modules\Core\Actions;

use App\Support\Actions\Action;
use Modules\Core\Exceptions\TaxInUse;
use Modules\Core\Models\Tax;

/**
 * Deletes a tax that no category uses. A tax that should stop applying is deactivated instead.
 */
class DeleteTax extends Action
{
    /**
     * @throws TaxInUse
     */
    public function handle(Tax $tax): void
    {
        $categories = $tax->categories()->pluck('name');

        if ($categories->isNotEmpty()) {
            throw new TaxInUse(__('":tax" is used by :categories. Remove it from them first, or deactivate it.', [
                'tax' => $tax->name, 'categories' => $categories->implode(', '),
            ]));
        }

        $tax->delete();
    }
}
