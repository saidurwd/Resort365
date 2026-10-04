<?php

namespace Modules\Reservation\Services;

use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Modules\Reservation\Contracts\RoomBlocks;
use Modules\Reservation\Enums\LockType;
use Modules\Reservation\Exceptions\RoomBlockRefused;
use Modules\Reservation\Models\InventoryLock;
use Modules\Reservation\Models\Reservation;

class RoomBlocksService implements RoomBlocks
{
    public function block(int $propertyId, int $roomId, string $from, string $to, int $blockId, string $note): void
    {
        $taken = InventoryLock::query()->where('room_id', $roomId)->where('stay_date', '>=', $from)->where('stay_date', '<', $to)
            ->where(fn ($query) => $query->whereNull('block_id')->orWhere('block_id', '!=', $blockId))->orderBy('stay_date')->get();

        if ($taken->isNotEmpty()) {
            throw new RoomBlockRefused($this->describe($taken->all()));
        }

        $now = now();
        $tenantId = app(TenantContext::class)->tenantOrFail(self::class)->id;
        $rows = [];

        for ($date = CarbonImmutable::parse($from); $date->lt(CarbonImmutable::parse($to)); $date = $date->addDay()) {
            $rows[] = [
                'tenant_id' => $tenantId, 'property_id' => $propertyId, 'room_id' => $roomId,
                'stay_date' => $date->toDateString(), 'lock_type' => LockType::OutOfOrder->value, 'block_id' => $blockId, 'note' => $note,
                'created_at' => $now, 'updated_at' => $now,
            ];
        }

        // The unique (room_id, stay_date) index decides when a booking was made meanwhile.
        try {
            DB::transaction(function () use ($blockId, $from, $to, $rows): void {
                InventoryLock::query()->where('block_id', $blockId)->where('stay_date', '>=', $from)->where('stay_date', '<', $to)->delete();
                InventoryLock::query()->insert($rows);
            });
        } catch (UniqueConstraintViolationException) {
            throw new RoomBlockRefused(__('The room was booked for some of these nights in the meantime.'));
        }
    }

    public function release(int $blockId, ?string $from = null): int
    {
        return InventoryLock::query()->where('block_id', $blockId)->where('lock_type', LockType::OutOfOrder->value)
            ->when($from !== null, fn ($query) => $query->where('stay_date', '>=', $from))->delete();
    }

    /**
     * @param  list<InventoryLock>  $locks
     */
    private function describe(array $locks): string
    {
        $codes = Reservation::query()->whereIn('id', array_filter(array_map(fn (InventoryLock $lock): ?int => $lock->reservation_id, $locks)))->pluck('code', 'id');
        $holders = collect($locks)->groupBy(fn (InventoryLock $lock): string => $lock->reservation_id !== null
            ? (string) ($codes[$lock->reservation_id] ?? '#'.$lock->reservation_id)
            : $lock->lock_type->label().($lock->note ? ' ('.$lock->note.')' : ''));

        return __('The room is taken: :list. Move or change those first.', ['list' => $holders->map(fn ($nights, string $holder): string => $holder.' '.__('on').' '
            .collect($nights)->map(fn (InventoryLock $lock): string => $lock->stay_date->format('d M'))->implode(', '))->implode('; ')]);
    }
}
