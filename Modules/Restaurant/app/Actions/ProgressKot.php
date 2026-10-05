<?php

namespace Modules\Restaurant\Actions;

use App\Support\Actions\Action;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Events\KotItemStatusChanged;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\KotLine;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\KotTransitions;

/**
 * A tap on the kitchen display (ARCHITECTURE §5.10.6): start, ready, bump or recall a ticket
 * (KotTransitions). The ticket's lines follow it, and so do their order lines (not voided ones):
 * preparing, ready to serve, served when bumped. The outlet's POS screens hear it (KotItemStatusChanged),
 * so the waiter knows a dish is ready.
 */
class ProgressKot extends Action
{
    public function __construct(
        private readonly KotTransitions $transitions,
    ) {}

    /**
     * @throws PosNotAllowed
     */
    public function handle(Kot $kot, string $action): Kot
    {
        return $this->transaction(function () use ($kot, $action): Kot {
            $locked = Kot::query()->lockForUpdate()->findOrFail($kot->id);
            $next = $this->transitions->next($locked->type, $locked->status, $action);

            if (! $next instanceof KotStatus) {
                throw new PosNotAllowed(__('Ticket :no is :status.', ['no' => $locked->kot_no, 'status' => mb_strtolower($locked->status->label())]));
            }

            $stamp = match ($next) {
                KotStatus::Preparing => ['started_at' => $locked->started_at ?? now()],
                KotStatus::Ready => ['ready_at' => now(), 'done_at' => null, 'started_at' => $locked->started_at ?? now()],
                KotStatus::Done => ['done_at' => now()],
                KotStatus::New => ['done_at' => null],
            };
            $locked->forceFill(['status' => $next, ...$stamp])->save();
            KotLine::query()->where('kot_id', $locked->id)->update(['status' => $next->value]);

            $lineIds = KotLine::query()->where('kot_id', $locked->id)->pluck('pos_order_line_id')->map(fn ($id): int => (int) $id)->all();

            if ($locked->type === KotType::New) {
                $lineStatus = match ($next) {
                    KotStatus::Preparing => OrderLineStatus::Preparing,
                    KotStatus::Ready => OrderLineStatus::Ready,
                    KotStatus::Done => OrderLineStatus::Served,
                    KotStatus::New => OrderLineStatus::Sent,
                };
                PosOrderLine::query()->whereIn('id', $lineIds)->where('status', '!=', OrderLineStatus::Voided->value)->update(['status' => $lineStatus->value]);
            }

            $order = $locked->order()->with('table')->firstOrFail();
            $lines = PosOrderLine::query()->whereIn('id', $lineIds)->where('status', '!=', OrderLineStatus::Voided->value)->orderBy('id')->get();

            KotItemStatusChanged::dispatch($locked->tenant_id, $locked->outlet_id, $locked->kitchen_station_id, $order->id, $locked->id, $next->value, $lineIds,
                $lines->map(fn (PosOrderLine $line): string => $line->quantity.' × '.$line->name_snapshot.($line->variant_snapshot ? ' ('.$line->variant_snapshot.')' : ''))->values()->all(),
                $order->order_no, $order->table?->number, $order->waiter_id);

            return $locked;
        });
    }
}
