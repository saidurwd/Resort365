<?php

namespace Modules\Restaurant\Services;

use Modules\FrontOffice\Contracts\NightAuditBlocker;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Models\PosSession;

/**
 * The night audit waits until every POS session of the property's business date (or earlier) is closed
 * (ARCHITECTURE §5.10.11), so the day's restaurant takings are complete.
 */
class OpenSessionsBlocker implements NightAuditBlocker
{
    public function blocking(int $propertyId, string $businessDate): array
    {
        return PosSession::query()->where('property_id', $propertyId)->where('status', PosSessionStatus::Open->value)->where('business_date', '<=', $businessDate)
            ->with(['terminal', 'outlet'])->orderBy('id')->get()
            ->map(fn (PosSession $session): string => __('The POS session on :terminal (:outlet) is still open: close it on the POS.', [
                'terminal' => $session->terminal->name, 'outlet' => $session->outlet->name,
            ]))->values()->all();
    }
}
