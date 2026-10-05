<?php

namespace Modules\Restaurant\Services;

use Illuminate\Support\Collection;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Restaurant\Enums\Allergen;
use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;
use Modules\Restaurant\Enums\OrderLineStatus;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\KotLine;
use Modules\Restaurant\Models\MenuItem;

/**
 * A station's kitchen display as JSON (ARCHITECTURE §10.3): its tickets not yet bumped, oldest first,
 * with table, waiter, lines (quantity, item, variant, modifiers, notes, seat, course), the items'
 * allergens, and lines voided since; plus the last bumped ticket (to recall) and the time thresholds
 * for the elapsed-time colours. The server's clock comes along so screens time tickets correctly.
 */
class KdsBoard
{
    public function __construct(
        private readonly UserDirectory $users,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(KitchenStation $station, int $warnMinutes, int $lateMinutes): array
    {
        $kots = Kot::query()->where('kitchen_station_id', $station->id)->where('status', '!=', KotStatus::Done->value)
            ->with(['lines.line', 'order.table'])->orderBy('fired_at')->orderBy('kot_no')->limit(100)->get();
        $last = Kot::query()->where('kitchen_station_id', $station->id)->where('status', KotStatus::Done->value)->where('done_at', '>=', now()->subMinutes(30))
            ->orderByDesc('done_at')->first();
        $allergens = MenuItem::query()->whereIn('id', $kots->flatMap(fn (Kot $kot): Collection => $kot->lines->map(fn (KotLine $line): int => $line->line->menu_item_id))->unique())
            ->pluck('allergens', 'id');
        $names = collect($this->users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]);

        return [
            'now' => now()->toIso8601String(),
            'warn' => $warnMinutes,
            'late' => $lateMinutes,
            'recall' => $last instanceof Kot ? ['id' => $last->id, 'no' => $last->kot_no] : null,
            'tickets' => $kots->map(fn (Kot $kot): array => [
                'id' => $kot->id, 'no' => $kot->kot_no, 'type' => $kot->type->value, 'status' => $kot->status->value,
                'fired_at' => $kot->fired_at->toIso8601String(), 'started_at' => $kot->started_at?->toIso8601String(),
                'order_no' => $kot->order->order_no, 'where' => $kot->order->table ? __('Table :number', ['number' => $kot->order->table->number]) : $kot->order->order_type->label(),
                'covers' => $kot->order->covers, 'waiter' => $names[$kot->order->waiter_id] ?? '',
                'lines' => $kot->lines->map(fn (KotLine $kotLine): array => [
                    'id' => $kotLine->line->id, 'quantity' => $kotLine->quantity, 'name' => $kotLine->line->name_snapshot, 'variant' => $kotLine->line->variant_snapshot,
                    'modifiers' => array_column($kotLine->line->modifiers ?? [], 'name'), 'notes' => $kotLine->line->notes, 'seat' => $kotLine->line->seat_no,
                    'course' => $kotLine->line->course->label(),
                    'allergens' => array_values(array_filter(array_map(fn (string $value): ?string => Allergen::tryFrom($value)?->label(), (array) ($allergens[$kotLine->line->menu_item_id] ?? [])))),
                    'voided' => $kot->type === KotType::New && $kotLine->line->status === OrderLineStatus::Voided,
                    'void_reason' => $kot->type === KotType::Void ? $kotLine->line->void_reason?->label() : null,
                ])->values()->all(),
            ])->values()->all(),
        ];
    }
}
