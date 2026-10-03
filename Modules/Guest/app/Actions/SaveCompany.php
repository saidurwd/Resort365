<?php

namespace Modules\Guest\Actions;

use App\Support\Actions\Action;
use Modules\Guest\Models\Company;

/**
 * Creates or updates a company (validated by SaveCompanyRequest).
 */
class SaveCompany extends Action
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(?Company $company, array $data): Company
    {
        $company ??= new Company;
        $company->fill($data)->save();

        return $company;
    }
}
