<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Models\Company;

/**
 * Deletes (soft) a company; existing links keep showing its name.
 */
class DeleteCompany extends Action
{
    public function handle(Company $company): void
    {
        $company->delete();
    }
}
