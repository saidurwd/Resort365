<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Events\KotSent;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\KotLine;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\KotGrouper;
use Modules\Restaurant\Services\PosNumbers;

/**
 * Sends an order to the kitchen (ARCHITECTURE §5.10.6): the pending lines not on hold — or, firing a
 * course, its held lines — are grouped by station into one KOT each, numbered per outlet and business
 * date, and become sent. Each station's kitchen display hears of its ticket at once (KotSent).
 */
class SendOrder extends Action
{
    public function __construct(
        private readonly KotGrouper $grouper,
        private readonly PosNumbers $numbers,
    ) {}

    /**
     * @return list<Kot> the tickets made
     *
     * @throws PosNotAllowed
     */
    public function handle(PosOrder $order, int $userId, ?Course $fire = null): array
    {
        return $this->transaction(function () use ($order, $userId, $fire): array {
            $locked = PosOrder::query()->lockForUpdate()->with('outlet')->findOrFail($order->id);

            if (! $locked->isOpen()) {
                throw new PosNotAllowed(__('This order is closed.'));
            }

            $lines = PosOrderLine::query()->where('pos_order_id', $locked->id)->orderBy('id')->get();
            $tickets = $this->grouper->group($lines->map(fn (PosOrderLine $line): array => [
                'id' => $line->id, 'station_id' => $line->kitchen_station_id, 'status' => $line->status->value, 'held' => $line->is_held, 'course' => $line->course->value,
            ])->all(), $fire?->value);

            if ($tickets === []) {
                throw new PosNotAllowed($fire instanceof Course ? __('Nothing is held for :course.', ['course' => mb_strtolower($fire->label())]) : __('Nothing new to send.'));
            }

            $now = now();
            $quantities = $lines->pluck('quantity', 'id');
            $kots = [];

            foreach ($tickets as $station => $lineIds) {
                $kot = Kot::query()->create([
                    'property_id' => $locked->property_id, 'outlet_id' => $locked->outlet_id, 'pos_order_id' => $locked->id,
                    'kitchen_station_id' => $station === 'none' ? null : (int) $station, 'kot_no' => $this->numbers->nextKotNo($locked->outlet, $locked->business_date->toDateString()),
                    'business_date' => $locked->business_date, 'type' => KotType::New, 'status' => KotStatus::New, 'fired_at' => $now, 'created_by' => $userId,
                ]);

                foreach ($lineIds as $lineId) {
                    KotLine::query()->create(['property_id' => $locked->property_id, 'kot_id' => $kot->id, 'pos_order_line_id' => $lineId, 'quantity' => $quantities[$lineId], 'status' => KotStatus::New]);
                }

                PosOrderLine::query()->whereIn('id', $lineIds)->update(['status' => OrderLineStatus::Sent->value, 'is_held' => false, 'sent_at' => $now]);
                $kots[] = $kot;
                KotSent::dispatch($locked->tenant_id, $locked->outlet_id, $kot->kitchen_station_id, $locked->id, [$kot->id]);
            }

            return $kots;
        });
    }
}
