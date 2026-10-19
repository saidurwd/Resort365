<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Attendance;
use Modules\HR\Models\Roster;
use Modules\HR\Models\Shift;

/**
 * Deletes a shift nobody has been rostered on (Step 6.2); otherwise it can only be made inactive.
 */
class DeleteShift extends Action
{
    /**
     * @throws HrRuleViolated
     */
    public function handle(Shift $shift): void
    {
        if (Roster::query()->where('shift_id', $shift->id)->exists() || Attendance::query()->where('shift_id', $shift->id)->exists()) {
            throw new HrRuleViolated(__('":name" has been used in rosters or attendance, so it can only be made inactive.', ['name' => $shift->name]));
        }

        $shift->delete();
    }
}
