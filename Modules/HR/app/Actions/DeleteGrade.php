<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Grade;

/**
 * Deletes a grade nobody holds (Step 6.1); otherwise it can only be made inactive.
 */
class DeleteGrade extends Action
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Grade $grade): void
    {
        if ($grade->employees()->withTrashed()->exists()) {
            throw new HrRuleViolated(__('":name" is held by employees, so it can only be made inactive.', ['name' => $grade->name]));
        }

        $grade->delete();
    }
}
