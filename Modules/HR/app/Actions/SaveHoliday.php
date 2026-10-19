<?php

namespace Modules\HR\Actions;

use App\Support\Actions\Action;
use Modules\HR\Exceptions\HrRuleViolated;
use Modules\HR\Models\Holiday;

/**
 * Creates or changes a holiday of the current property (Step 6.2); one holiday per date. Console code may pass the
 * property as `property_id`.
 */
class SaveHoliday extends Action
{
    /**
     * @param  array<string, mixed>  $data  name, holiday_date, is_paid, repeats_yearly, property_id (new holidays, console only)
     *
     * @throws HrRuleViolated
     */
    public function handle(?Holiday $holiday, array $data): Holiday
    {
        $date = (string) $data['holiday_date'];
        $propertyId = $holiday instanceof Holiday ? $holiday->property_id : (isset($data['property_id']) ? (int) $data['property_id'] : null);

        if (Holiday::query()->whereDate('holiday_date', $date)->when($propertyId !== null, fn ($query) => $query->where('property_id', $propertyId))->when($holiday instanceof Holiday, fn ($query) => $query->whereKeyNot($holiday?->id))->exists()) {
            throw new HrRuleViolated(__('There is already a holiday on :date.', ['date' => date('d M Y', (int) strtotime($date))]));
        }

        $holiday ??= new Holiday;
        $values = ['name' => trim((string) $data['name']), 'holiday_date' => $date, 'is_paid' => (bool) ($data['is_paid'] ?? true), 'repeats_yearly' => (bool) ($data['repeats_yearly'] ?? false)];

        if (! $holiday->exists && $propertyId !== null) {
            $values['property_id'] = $propertyId;
        }

        $holiday->fill($values)->save();

        return $holiday;
    }
}
