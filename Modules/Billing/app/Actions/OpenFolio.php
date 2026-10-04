<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\Folio;
use Modules\Billing\Services\FolioLedger;
use Modules\Guest\Contracts\GuestLookup;

/**
 * Opens another folio for a reservation: a company or travel-agent folio (the account pays), or a
 * master folio. Its name is the account's (or the guest's).
 */
class OpenFolio extends Action
{
    public function __construct(
        private readonly FolioLedger $ledger,
        private readonly GuestLookup $guests,
    ) {}

    /**
     * @throws ChargeRejected
     */
    public function handle(int $reservationId, FolioType $type, BillTo $billTo, ?int $billToId = null): Folio
    {
        $reservation = $this->ledger->reservation($reservationId);

        $name = match ($billTo) {
            BillTo::Company => $this->guests->findCompany((int) $billToId)->name ?? throw new ChargeRejected(__('Unknown company.')),
            BillTo::TravelAgent => $this->guests->findTravelAgent((int) $billToId)->name ?? throw new ChargeRejected(__('Unknown travel agent.')),
            BillTo::Guest => $this->guests->find($reservation->primaryGuestId)->name ?? __('Guest'),
        };

        return $this->transaction(fn (): Folio => $this->ledger->open($reservation, $type, $billTo, $billTo === BillTo::Guest ? $reservation->primaryGuestId : $billToId,
            $type === FolioType::Master ? __('Master — :name', ['name' => $reservation->groupName ?? $name]) : $name), attempts: 3);
    }
}
