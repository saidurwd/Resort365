<?php

namespace Modules\Billing\Services;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\ExtraService;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioRoutingRule;
use Modules\Guest\Contracts\GuestLookup;
use Modules\Guest\DTOs\CompanySummary;
use Modules\Guest\DTOs\TravelAgentSummary;
use Modules\IAM\Contracts\UserDirectory;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

/**
 * The Folios tab of the reservation page (registered through Reservation's ReservationTabs): each
 * folio with its lines and balance, and the forms to post, adjust, void, open folios and route.
 * A booking made before folios existed gets its guest folio when the tab is first opened.
 */
class FoliosTab
{
    public function __construct(
        private readonly ReservationLookup $reservations,
        private readonly FolioLedger $ledger,
        private readonly GuestLookup $guests,
        private readonly UserDirectory $users,
    ) {}

    public function render(int $reservationId): View|string
    {
        $reservation = $this->reservations->find($reservationId);

        if (! $reservation instanceof ReservationSummary) {
            return '';
        }

        if (! Folio::query()->where('reservation_id', $reservationId)->exists()) {
            DB::transaction(fn (): Folio => $this->ledger->guestFolio($reservationId), 3);
        }

        $userNames = [];

        foreach ($this->users->all() as $user) {
            $userNames[$user->id] = $user->name;
        }

        return view('billing::folios.tab', [
            'reservation' => $reservation,
            'folios' => Folio::query()->with(['lines.chargeCode'])->where('reservation_id', $reservationId)->orderBy('id')->get(),
            'routes' => FolioRoutingRule::query()->where('reservation_id', $reservationId)->get()->keyBy(fn (FolioRoutingRule $rule): string => $rule->category->value),
            'extras' => ExtraService::query()->where('property_id', $reservation->propertyId)->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
            'codes' => ChargeCode::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'categories' => ChargeCategory::cases(),
            'folioTypes' => [FolioType::Company, FolioType::Master],
            'billTo' => BillTo::cases(),
            'companies' => array_map(fn (CompanySummary $company): array => ['id' => $company->id, 'name' => $company->name], $this->guests->searchCompanies('', 200)),
            'agents' => array_map(fn (TravelAgentSummary $agent): array => ['id' => $agent->id, 'name' => $agent->name], $this->guests->searchTravelAgents('', 200)),
            'userNames' => $userNames,
        ]);
    }
}
