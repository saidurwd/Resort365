<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Rates\Actions\DeleteSeason;
use Modules\Rates\Actions\SaveSeason;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\SaveSeasonRequest;
use Modules\Rates\Models\Season;

class SeasonController extends Controller
{
    use UsesCurrentProperty;

    public function index(): View
    {
        Gate::authorize('viewAny', Season::class);

        return view('rates::seasons.index', [
            'propertyName' => $this->currentPropertyName(),
            'seasons' => Season::query()->where('property_id', $this->currentPropertyId())->with('periods')->withCount('rates')
                ->orderByDesc('priority')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Season::class);
        $this->currentPropertyId();

        return view('rates::seasons.form', ['season' => null, 'history' => []]);
    }

    public function store(SaveSeasonRequest $request, SaveSeason $save): RedirectResponse
    {
        $season = $save->handle(null, $request->validated());

        return to_route('rates.seasons.index')->with('success', __('Season ":name" created.', ['name' => $season->name]));
    }

    public function edit(Season $season): View
    {
        Gate::authorize('update', $season);

        return view('rates::seasons.form', ['season' => $season->load('periods'), 'history' => app(AuditTrail::class)->for($season)]);
    }

    public function update(SaveSeasonRequest $request, Season $season, SaveSeason $save): RedirectResponse
    {
        $save->handle($season, $request->validated());

        return to_route('rates.seasons.index')->with('success', __('Season ":name" saved.', ['name' => $season->name]));
    }

    public function destroy(Season $season, DeleteSeason $delete): RedirectResponse
    {
        Gate::authorize('delete', $season);
        $delete->handle($season);

        return to_route('rates.seasons.index')->with('success', __('Season ":name" deleted.', ['name' => $season->name]));
    }
}
