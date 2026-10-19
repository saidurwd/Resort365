<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Models\Attendance;

/**
 * Removes a day's attendance record (Step 6.2), e.g. a wrong import; the report then treats a rostered day as absent.
 */
class DeleteAttendance extends Action
{
    public function handle(Attendance $attendance): void
    {
        $attendance->delete();
    }
}
