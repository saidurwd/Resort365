<?php

namespace Modules\Guest\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\Settings;
use Modules\Guest\Actions\DeleteTravelAgent;
use Modules\Guest\Actions\SaveTravelAgent;
use Modules\Guest\Http\Requests\SaveTravelAgentRequest;
use Modules\Guest\Models\TravelAgent;

class TravelAgentController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function index(): View
    {
        Gate::authorize('viewAny', TravelAgent::class);

        return view('guest::travel-agents.index', ['records' => TravelAgent::query()->orderBy('name')->get(), 'currency' => $this->currency()]);
    }

    public function create(): View
    {
        Gate::authorize('create', TravelAgent::class);

        return view('guest::travel-agents.form', ['record' => null, 'currency' => $this->currency(), 'history' => []]);
    }

    public function store(SaveTravelAgentRequest $request, SaveTravelAgent $save): RedirectResponse
    {
        $record = $save->handle(null, $request->validated());

        return to_route('guest.travel-agents.index')->with('success', __('Travel agent ":name" created.', ['name' => $record->name]));
    }

    public function edit(TravelAgent $travelAgent): View
    {
        Gate::authorize('update', $travelAgent);

        return view('guest::travel-agents.form', ['record' => $travelAgent, 'currency' => $this->currency(), 'history' => app(AuditTrail::class)->for($travelAgent)]);
    }

    public function update(SaveTravelAgentRequest $request, TravelAgent $travelAgent, SaveTravelAgent $save): RedirectResponse
    {
        $save->handle($travelAgent, $request->validated());

        return to_route('guest.travel-agents.index')->with('success', __('Travel agent ":name" saved.', ['name' => $travelAgent->name]));
    }

    public function destroy(TravelAgent $travelAgent, DeleteTravelAgent $delete): RedirectResponse
    {
        Gate::authorize('delete', $travelAgent);
        $delete->handle($travelAgent);

        return to_route('guest.travel-agents.index')->with('success', __('Travel agent ":name" deleted.', ['name' => $travelAgent->name]));
    }

    private function currency(): string
    {
        return (string) $this->settings->get('core.currency');
    }
}
