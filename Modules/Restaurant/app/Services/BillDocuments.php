<?php

namespace Modules\Restaurant\Services;

use App\Support\Tenancy\TenantStorage;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Models\PosBill;
use Modules\Restaurant\Models\PosPayment;

/**
 * A bill (pre-check) or receipt on 80 mm paper, for the POS and the back office (a guest's folio links to
 * it). Every receipt after the first is marked COPY; a room charge shows the guest's signature, else a
 * line to sign on.
 */
class BillDocuments
{
    public function __construct(
        private readonly PropertyDirectory $properties,
        private readonly TenantStorage $storage,
    ) {}

    public function render(PosBill $bill, bool $receipt): View
    {
        $bill->load(['lines', 'payments', 'outlet', 'order.table']);

        return view('restaurant::pos.bill-print', [
            'bill' => $bill, 'receipt' => $receipt, 'copy' => $receipt ? $bill->receipt_count > 1 : $bill->print_count > 1,
            'property' => $this->properties->find($bill->property_id), 'inclusive' => $bill->outlet->prices_include_tax,
            'signatures' => $bill->payments->filter(fn (PosPayment $payment): bool => $payment->signature_path !== null)
                ->mapWithKeys(fn (PosPayment $payment): array => [$payment->id => 'data:image/png;base64,'.base64_encode((string) $this->storage->get((string) $payment->signature_path))])->all(),
        ]);
    }

    public function receipt(PosBill $bill): View
    {
        $bill->forceFill(['receipt_count' => $bill->receipt_count + 1])->save();

        return $this->render($bill, true);
    }
}
