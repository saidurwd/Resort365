<?php

namespace Modules\Billing\DTOs;

use App\Support\DTOs\Data;

/**
 * An in-house booking an outlet may charge (FolioPostingContract::chargeableStays): who, which rooms,
 * the folio its outlet charges go to, its balance and the credit left (null = no limit), and whether
 * the booking takes room charges at all.
 */
final readonly class ChargeableStay extends Data
{
    /**
     * @param  list<string>  $rooms
     */
    public function __construct(
        public int $reservationId,
        public string $code,
        public string $guestName,
        public array $rooms,
        public int $folioId,
        public string $folioNo,
        public string $balance,
        public ?string $creditLeft,
        public bool $noRoomCharges,
        public string $checkOut,
    ) {}
}
