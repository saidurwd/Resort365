<?php

namespace Modules\Billing\Actions;

use App\Support\Actions\Action;
use Carbon\CarbonImmutable;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioLineType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;
use Modules\Billing\Services\DefaultChargeCodes;
use Modules\Billing\Services\FolioLedger;
use Modules\Billing\Services\TaxSplitter;
use Modules\Reservation\DTOs\RoomNightCharge;

/**
 * Posts a reservation's room nights to its folios at the prices frozen at booking (net, tax, total
 * per night), with the ROOM charge code and routed like any room charge. Each line references its
 * night, so a night already on a folio is never posted again (check-out now, night audit later).
 */
class PostRoomNights extends Action
{
    public const string REFERENCE = 'reservation_item_night';

    public function __construct(
        private readonly FolioLedger $ledger,
        private readonly TaxSplitter $splitter,
        private readonly DefaultChargeCodes $defaults,
    ) {}

    /**
     * @param  list<RoomNightCharge>  $nights
     * @return list<int> the night ids now on a folio (posted now or before)
     *
     * @throws ChargeRejected
     */
    public function handle(int $reservationId, array $nights, ?int $userId = null): array
    {
        if ($nights === []) {
            return [];
        }

        return $this->transaction(function () use ($reservationId, $nights, $userId): array {
            $code = ChargeCode::query()->where('code', 'ROOM')->first();

            if (! $code instanceof ChargeCode) {
                $this->defaults->ensure();
                $code = ChargeCode::query()->where('code', 'ROOM')->firstOrFail();
            }

            $folio = Folio::query()->lockForUpdate()->findOrFail($this->ledger->folioFor($reservationId, ChargeCategory::Room)->id);

            if (! $folio->isOpen()) {
                throw new ChargeRejected(__('Folio :no is :status.', ['no' => $folio->folio_no, 'status' => strtolower($folio->status->label())]));
            }

            $guestFolioId = $this->ledger->guestFolio($reservationId)->id;
            $already = FolioLine::query()->where('reference_type', self::REFERENCE)->whereIn('reference_id', array_map(fn (RoomNightCharge $night): int => $night->nightId, $nights))
                ->where('is_voided', false)->pluck('reference_id')->map(fn ($id): int => (int) $id)->all();
            $date = $this->ledger->businessDate($folio->property_id);

            foreach ($nights as $night) {
                if (in_array($night->nightId, $already, true)) {
                    continue;
                }

                FolioLine::query()->create([
                    'property_id' => $folio->property_id,
                    'folio_id' => $folio->id,
                    'posting_date' => $date,
                    'line_type' => FolioLineType::Charge,
                    'charge_code_id' => $code->id,
                    'description' => __(':unit · night of :date', ['unit' => $night->label, 'date' => CarbonImmutable::parse($night->date)->format('D d M')]),
                    'quantity' => '1',
                    'unit_price' => $night->net,
                    'amount' => $night->net,
                    'tax_amount' => $night->tax,
                    'tax_lines' => $this->splitter->split($night->net, $night->taxCategoryId, $night->tax),
                    'total' => $night->total,
                    'reference_type' => self::REFERENCE,
                    'reference_id' => $night->nightId,
                    'routed_from_folio_id' => $guestFolioId !== $folio->id ? $guestFolioId : null,
                    'posted_by' => $userId,
                ]);
            }

            $this->ledger->recalculate($folio);

            return array_map(fn (RoomNightCharge $night): int => $night->nightId, $nights);
        }, attempts: 3);
    }
}
