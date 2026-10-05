<?php

namespace Modules\Restaurant\Services;

use Modules\Restaurant\Enums\KotStatus;
use Modules\Restaurant\Enums\KotType;

/**
 * What a tap on the kitchen display does to a ticket (ARCHITECTURE §5.10.6, §10.3), without the
 * database. A new ticket: start (new → preparing), ready (new or preparing → ready), bump (ready →
 * done) and recall (done → ready, the last bumped ticket back on the board). A void ticket is only
 * acknowledged: bump (new → done) and recall (done → new).
 */
class KotTransitions
{
    public const array ACTIONS = ['start', 'ready', 'bump', 'recall'];

    /**
     * The ticket's status after the action, or null when it is not allowed.
     */
    public function next(KotType $type, KotStatus $status, string $action): ?KotStatus
    {
        if ($type === KotType::Void) {
            return match ([$action, $status]) {
                ['bump', KotStatus::New] => KotStatus::Done,
                ['recall', KotStatus::Done] => KotStatus::New,
                default => null,
            };
        }

        return match (true) {
            $action === 'start' && $status === KotStatus::New => KotStatus::Preparing,
            $action === 'ready' && in_array($status, [KotStatus::New, KotStatus::Preparing], true) => KotStatus::Ready,
            $action === 'bump' && $status === KotStatus::Ready => KotStatus::Done,
            $action === 'recall' && $status === KotStatus::Done => KotStatus::Ready,
            default => null,
        };
    }
}
