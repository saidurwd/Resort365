<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Models\MenuSchedule;
use Modules\Restaurant\Models\Outlet;

/**
 * Creates or changes a menu schedule of an outlet (breakfast, happy hour…).
 */
class SaveMenuSchedule extends Action
{
    /**
     * @param  array{name: string, days_of_week: list<string>, start_time: string, end_time: string, price_adjustment_percent?: string|null, is_active?: bool}  $data
     */
    public function handle(Outlet $outlet, ?MenuSchedule $schedule, array $data): MenuSchedule
    {
        $schedule ??= new MenuSchedule(['property_id' => $outlet->property_id, 'outlet_id' => $outlet->id]);
        $schedule->fill([
            'name' => $data['name'], 'days_of_week' => array_values(array_unique($data['days_of_week'])), 'start_time' => $data['start_time'], 'end_time' => $data['end_time'],
            'price_adjustment_percent' => $data['price_adjustment_percent'] ?? '0' ?: '0', 'is_active' => $data['is_active'] ?? true,
        ])->save();

        return $schedule;
    }
}
