<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Billing\Actions\OpenFolio;
use Modules\Billing\Actions\PostAdjustment;
use Modules\Billing\Actions\PostCharge;
use Modules\Billing\Actions\SaveRoutingRule;
use Modules\Billing\Actions\VoidFolioLine;
use Modules\Billing\Enums\BillTo;
use Modules\Billing\Enums\ChargeCategory;
use Modules\Billing\Enums\FolioType;
use Modules\Billing\Exceptions\ChargeRejected;
use Modules\Billing\Http\Requests\OpenFolioRequest;
use Modules\Billing\Http\Requests\PostAdjustmentRequest;
use Modules\Billing\Http\Requests\PostChargeRequest;
use Modules\Billing\Http\Requests\SaveRoutingRuleRequest;
use Modules\Billing\Http\Requests\VoidFolioLineRequest;
use Modules\Billing\Models\Folio;
use Modules\Billing\Models\FolioLine;

/**
 * The Folios tab of the reservation page: open a folio, post charges and adjustments, void lines,
 * route charges. Every action returns to the tab.
 */
class FolioController extends Controller
{
    public function store(OpenFolioRequest $request, OpenFolio $open): RedirectResponse
    {
        $reservationId = (int) $request->validated('reservation_id');

        try {
            $folio = $open->handle($reservationId, FolioType::from((string) $request->validated('type')), BillTo::from((string) $request->validated('bill_to_type')), $request->billToId());
        } catch (ChargeRejected $exception) {
            return $this->back($reservationId)->with('error', $exception->getMessage());
        }

        return $this->back($reservationId)->with('success', __('Folio :no opened.', ['no' => $folio->folio_no]));
    }

    public function charge(PostChargeRequest $request, Folio $folio, PostCharge $post): RedirectResponse
    {
        Gate::authorize('post', $folio);

        try {
            $line = $post->handle($request->charge());
        } catch (ChargeRejected $exception) {
            return $this->back((int) $folio->reservation_id)->withInput()->withErrors(['charge' => $exception->getMessage()], 'folio');
        }

        return $this->back((int) $folio->reservation_id)->with('success', __(':description posted (:total).', ['description' => $line->description, 'total' => number_format((float) $line->total, 2)]));
    }

    public function adjust(PostAdjustmentRequest $request, Folio $folio, PostAdjustment $adjust): RedirectResponse
    {
        Gate::authorize('adjust', $folio);
        $code = $request->validated('charge_code_id');

        try {
            $adjust->handle($folio, (string) $request->validated('amount'), (string) $request->validated('reason'), is_numeric($code) ? (int) $code : null, $this->userId());
        } catch (ChargeRejected $exception) {
            return $this->back((int) $folio->reservation_id)->withErrors(['adjustment' => $exception->getMessage()], 'folio');
        }

        return $this->back((int) $folio->reservation_id)->with('success', __('Adjustment posted.'));
    }

    public function void(VoidFolioLineRequest $request, Folio $folio, VoidFolioLine $void): RedirectResponse
    {
        Gate::authorize('void', $folio);
        $line = FolioLine::query()->where('folio_id', $folio->id)->findOrFail((int) $request->validated('folio_line_id'));

        try {
            $void->handle($line, (string) $request->validated('reason'), $this->userId());
        } catch (ChargeRejected $exception) {
            return $this->back((int) $folio->reservation_id)->with('error', $exception->getMessage());
        }

        return $this->back((int) $folio->reservation_id)->with('success', __('":description" voided.', ['description' => $line->description]));
    }

    public function route(SaveRoutingRuleRequest $request, SaveRoutingRule $save): RedirectResponse
    {
        $reservationId = (int) $request->validated('reservation_id');
        $target = Folio::query()->findOrFail((int) $request->validated('target_folio_id'));
        Gate::authorize('post', $target);

        try {
            $save->handle($reservationId, ChargeCategory::from((string) $request->validated('category')), $target);
        } catch (ChargeRejected $exception) {
            return $this->back($reservationId)->with('error', $exception->getMessage());
        }

        return $this->back($reservationId)->with('success', __('From now on, :category charges go to folio :no.', [
            'category' => ChargeCategory::from((string) $request->validated('category'))->label(), 'no' => $target->folio_no,
        ]));
    }

    private function back(int $reservationId): RedirectResponse
    {
        return redirect()->to(route('reservation.bookings.show', $reservationId).'#folios');
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return is_numeric($id) ? (int) $id : null;
    }
}
