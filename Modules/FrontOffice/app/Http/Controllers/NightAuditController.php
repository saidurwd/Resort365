<?php

namespace Modules\FrontOffice\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\FrontOffice\Actions\RunNightAudit;
use Modules\FrontOffice\Enums\NightAuditStatus;
use Modules\FrontOffice\Enums\NightAuditTrigger;
use Modules\FrontOffice\Exceptions\NightAuditNotPossible;
use Modules\FrontOffice\Http\Requests\RunNightAuditRequest;
use Modules\FrontOffice\Models\NightAudit;
use Modules\FrontOffice\Services\NightAuditChecks;

/**
 * Front Office → Night audit (ARCHITECTURE §5.7): what the audit of the current property's business
 * date will find, running it, and the audits done so far with what each step did.
 */
class NightAuditController extends Controller
{
    public function index(NightAuditChecks $checks): View
    {
        Gate::authorize('viewAny', NightAudit::class);
        $propertyId = $this->propertyId();

        return view('frontoffice::night-audit.index', $checks->preview($propertyId) + [
            'history' => NightAudit::query()->where('property_id', $propertyId)->latest('business_date')->limit(14)->get(),
        ]);
    }

    public function store(RunNightAuditRequest $request, RunNightAudit $run, NightAuditChecks $checks): RedirectResponse
    {
        $propertyId = $this->propertyId();

        if ($checks->preview($propertyId)['date'] !== $request->validated('business_date')) {
            return to_route('frontoffice.night-audit.index')->with('error', __('The business date has changed since the page was opened: check again.'));
        }

        try {
            $audit = $run->handle($propertyId, NightAuditTrigger::Manual, (int) $request->user()?->getAuthIdentifier());
        } catch (NightAuditNotPossible $exception) {
            return to_route('frontoffice.night-audit.index')->with('error', $exception->getMessage());
        }

        return match ($audit->status) {
            NightAuditStatus::Completed => to_route('frontoffice.night-audit.show', $audit)->with('success', __('Night audit of :date done.', ['date' => $audit->business_date->format('d M Y')])),
            NightAuditStatus::Blocked => to_route('frontoffice.night-audit.index')->with('error', __('The night audit could not run: see the checks below.')),
            default => to_route('frontoffice.night-audit.show', $audit)->with('error', __('The night audit failed; nothing was changed. :error', ['error' => $audit->error])),
        };
    }

    public function show(NightAudit $audit): View
    {
        Gate::authorize('view', $audit);

        return view('frontoffice::night-audit.show', ['audit' => $audit]);
    }

    private function propertyId(): int
    {
        return app(PropertyContext::class)->currentId() ?? abort(403, __('Choose a property first.'));
    }
}
