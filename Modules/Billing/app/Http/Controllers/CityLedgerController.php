<?php

namespace Modules\Billing\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Billing\Actions\ReceiveCityLedgerPayment;
use Modules\Billing\Actions\TransferToCityLedger;
use Modules\Billing\Enums\CityLedgerStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Exceptions\PaymentNotAllowed;
use Modules\Billing\Http\Requests\LedgerPaymentRequest;
use Modules\Billing\Http\Requests\TransferToLedgerRequest;
use Modules\Billing\Models\CityLedgerEntry;
use Modules\Billing\Models\Folio;
use Modules\Billing\Services\AgingCalculator;
use Modules\Guest\Contracts\GuestLookup;

/**
 * The city ledger (company accounts receivable): open amounts per company with aging, receiving
 * payments, and moving a folio's balance onto a company's account.
 */
class CityLedgerController extends Controller
{
    public function index(AgingCalculator $aging, GuestLookup $guests): View
    {
        Gate::authorize('billing.city-ledger.view');
        $today = CarbonImmutable::today();
        $entries = CityLedgerEntry::query()->where('status', CityLedgerStatus::Open->value)->orderBy('due_on')->get();

        $companies = $entries->groupBy('company_id')->map(fn ($rows, $companyId): array => [
            'company' => $guests->findCompany((int) $companyId),
            'entries' => $rows,
            'aging' => $aging->buckets($rows->map(fn (CityLedgerEntry $entry): array => ['open' => $entry->open(), 'due_on' => $entry->due_on]), $today),
        ])->sortBy(fn (array $row): string => $row['company']->name ?? '')->values();

        return view('billing::city-ledger.index', [
            'companies' => $companies,
            'totals' => $aging->buckets($entries->map(fn (CityLedgerEntry $entry): array => ['open' => $entry->open(), 'due_on' => $entry->due_on]), $today),
            'buckets' => AgingCalculator::BUCKETS,
            'methods' => PaymentMethod::options(),
        ]);
    }

    public function receive(LedgerPaymentRequest $request, CityLedgerEntry $entry, ReceiveCityLedgerPayment $receive): RedirectResponse
    {
        try {
            $payment = $receive->handle($entry, (string) $request->validated('amount'), PaymentMethod::from((string) $request->validated('method')),
                $request->filled('reference') ? (string) $request->validated('reference') : null, $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null);
        } catch (PaymentNotAllowed $exception) {
            return to_route('billing.city-ledger.index')->with('error', $exception->getMessage());
        }

        return to_route('billing.city-ledger.index')->with('success', __('Payment :receipt received.', ['receipt' => $payment->receipt_no]));
    }

    public function transfer(TransferToLedgerRequest $request, Folio $folio, TransferToCityLedger $transfer): RedirectResponse
    {
        Gate::authorize('post', $folio);
        $back = $request->returnTo() ?? route('reservation.bookings.show', (int) $folio->reservation_id).'#folios';

        try {
            $entry = $transfer->handle($folio, (int) $request->validated('company_id'), $request->user() !== null ? (int) $request->user()->getAuthIdentifier() : null);
        } catch (ChargeRejected $exception) {
            return redirect()->to($back)->with('error', $exception->getMessage());
        }

        return redirect()->to($back)->with('success', __(':amount moved to the city ledger, due :date.', ['amount' => number_format((float) $entry->amount, 2), 'date' => $entry->due_on->format('d M Y')]));
    }
}
