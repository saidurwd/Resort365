<?php

namespace Modules\Billing\Services;

use Brick\Math\BigDecimal;
use Modules\Billing\Actions\PostCharge;
use Modules\Billing\Actions\VoidFolioLine;
use Modules\Billing\Contracts\FolioPostingContract;
use Modules\Billing\DTOs\ChargeableStay;
use Modules\Billing\DTOs\FolioCharge;
use Modules\Billing\DTOs\FolioPosting;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Reservation\Contracts\ReservationLookup;
use Modules\Reservation\DTOs\ReservationSummary;

class FolioPostingService implements FolioPostingContract
{
    public function __construct(
        private readonly PostCharge $post,
        private readonly VoidFolioLine $void,
        private readonly ReservationLookup $reservations,
        private readonly FolioLedger $ledger,
    ) {}

    public function postCharge(FolioCharge $charge): FolioPosting
    {
        $line = $this->post->handle($charge, requireInHouse: true, enforceCreditLimit: true);
        $folio = Folio::query()->findOrFail($line->folio_id);

        return new FolioPosting($line->id, $folio->id, $folio->folio_no, $line->total, $folio->balance);
    }

    public function chargeableStays(int $propertyId, ?string $term = null): array
    {
        $needle = mb_strtolower(trim((string) $term));
        $stays = array_filter($this->reservations->inHouse($propertyId), fn (ReservationSummary $stay): bool => $needle === ''
            || str_contains(mb_strtolower($stay->code), $needle) || str_contains(mb_strtolower($stay->guestName), $needle)
            || str_contains(mb_strtolower((string) $stay->groupName), $needle)
            || array_filter($stay->units, fn (string $unit): bool => str_contains(mb_strtolower($unit), $needle)) !== []);

        return array_map(function (ReservationSummary $stay): ChargeableStay {
            $folio = $this->ledger->folioFor($stay->id, ChargeCategory::FoodBeverage);
            $limit = $this->post->creditLimit($folio);
            $left = $limit !== null && BigDecimal::of($limit)->isPositive() ? (string) BigDecimal::of($limit)->minus($folio->balance)->toScale(2) : null;

            return new ChargeableStay($stay->id, $stay->code, $stay->groupName ?? $stay->guestName, $stay->units, $folio->id, $folio->folio_no,
                (string) $folio->balance, $left, $stay->noRoomCharges, $stay->checkOut);
        }, array_slice(array_values($stays), 0, 20));
    }

    public function chargeCodeId(string $code): ?int
    {
        $id = ChargeCode::query()->where('code', $code)->where('is_active', true)->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    public function reverseCharge(int $folioLineId, string $reason, ?int $userId = null): void
    {
        $line = FolioLine::query()->find($folioLineId) ?? throw new ChargeRejected(__('That folio line no longer exists.'));
        $this->void->handle($line, $reason, $userId);
    }
}
