<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\DTOs\ShiftTimes;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Shift;

/**
 * Creates or changes a shift (Step 6.2). The code is unique; an end time at or before the start runs past midnight; the
 * unpaid break must be shorter than the shift. Changing a shift does not re-calculate attendance already recorded.
 */
class SaveShift extends Action
{
    /**
     * @param  array<string, mixed>  $data  code, name, starts_at, ends_at, break_minutes, grace_minutes, overtime_after_minutes, color, is_active
     *
     * @throws HrRuleViolated
     */
    public function handle(?Shift $shift, array $data): Shift
    {
        $code = strtoupper(trim((string) $data['code']));

        if (Shift::query()->where('code', $code)->when($shift instanceof Shift, fn ($query) => $query->whereKeyNot($shift?->id))->exists()) {
            throw new HrRuleViolated(__('There is already a shift with the code :code.', ['code' => $code]));
        }

        $times = new ShiftTimes(substr((string) $data['starts_at'], 0, 5), substr((string) $data['ends_at'], 0, 5), (int) ($data['break_minutes'] ?? 0));

        if ($times->start === $times->end) {
            throw new HrRuleViolated(__('A shift cannot start and end at the same time.'));
        }

        if ($times->breakMinutes >= $times->endMinutes() - $times->startMinutes()) {
            throw new HrRuleViolated(__('The break must be shorter than the shift.'));
        }

        $shift ??= new Shift;
        $shift->fill([
            'code' => $code, 'name' => trim((string) $data['name']), 'starts_at' => $times->start.':00', 'ends_at' => $times->end.':00', 'break_minutes' => $times->breakMinutes,
            'grace_minutes' => (int) ($data['grace_minutes'] ?? 0), 'overtime_after_minutes' => (int) ($data['overtime_after_minutes'] ?? 0), 'color' => (string) ($data['color'] ?? 'primary'),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ])->save();

        return $shift;
    }
}
