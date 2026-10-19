<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Grade;

/**
 * Creates or changes a grade (Step 6.1); codes are unique per tenant.
 */
class SaveGrade extends Action
{
    /**
     * @param  array<string, mixed>  $data  code, name, level, is_active
     *
     * @throws HrRuleViolated
     */
    public function handle(?Grade $grade, array $data): Grade
    {
        $code = strtoupper(trim((string) $data['code']));

        if (Grade::query()->where('code', $code)->when($grade instanceof Grade, fn ($query) => $query->whereKeyNot($grade?->id))->exists()) {
            throw new HrRuleViolated(__('There is already a grade with the code :code.', ['code' => $code]));
        }

        $grade ??= new Grade;
        $grade->fill(['code' => $code, 'name' => trim((string) $data['name']), 'level' => (int) ($data['level'] ?? 1), 'is_active' => (bool) ($data['is_active'] ?? true)])->save();

        return $grade;
    }
}
