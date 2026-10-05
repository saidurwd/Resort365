<?php

namespace Modules\Restaurant\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Modules\Restaurant\Actions\RegisterStationDisplay;
use Modules\Restaurant\Actions\RegisterTerminal;
use Modules\Restaurant\Actions\SaveDiningArea;
use Modules\Restaurant\Actions\SaveDiningTable;
use Modules\Restaurant\Actions\SaveFloorPlan;
use Modules\Restaurant\Actions\SaveStation;
use Modules\Restaurant\Enums\TableShape;
use Modules\Restaurant\Exceptions\RestaurantSetupInvalid;
use Modules\Restaurant\Http\Requests\DiningAreaRequest;
use Modules\Restaurant\Http\Requests\DiningTableRequest;
use Modules\Restaurant\Http\Requests\FloorPlanRequest;
use Modules\Restaurant\Http\Requests\StationRequest;
use Modules\Restaurant\Http\Requests\TerminalRequest;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Models\PosTerminal;

/**
 * The parts of an outlet, edited on its setup page: kitchen stations, POS terminals, dining areas,
 * tables and the floor-plan positions (JSON from the drag-and-drop editor).
 */
class OutletSetupController extends Controller
{
    public function storeStation(StationRequest $request, Outlet $outlet, SaveStation $save): RedirectResponse
    {
        return $this->station($request, $outlet, null, $save);
    }

    public function updateStation(StationRequest $request, Outlet $outlet, KitchenStation $station, SaveStation $save): RedirectResponse
    {
        return $this->station($request, $outlet, $station, $save);
    }

    public function destroyStation(Outlet $outlet, KitchenStation $station): RedirectResponse
    {
        Gate::authorize('update', $outlet);
        $station->delete();

        return $this->back($outlet, 'stations', __('Station removed.'));
    }

    public function storeTerminal(TerminalRequest $request, Outlet $outlet, RegisterTerminal $register): RedirectResponse
    {
        [$terminal, $token] = $register->handle($outlet, (string) $request->validated('name'), $request->filled('receipt_printer_id') ? (int) $request->validated('receipt_printer_id') : null);

        return $this->back($outlet, 'terminals', __('Terminal :name registered.', ['name' => $terminal->name]))->with('terminal_token', ['id' => $terminal->id, 'token' => $token]);
    }

    public function updateTerminal(TerminalRequest $request, Outlet $outlet, PosTerminal $terminal): RedirectResponse
    {
        $terminal->fill([
            'name' => $request->validated('name'), 'receipt_printer_id' => $request->filled('receipt_printer_id') ? (int) $request->validated('receipt_printer_id') : null,
            'is_active' => $request->boolean('is_active'),
        ])->save();

        return $this->back($outlet, 'terminals', __('Terminal saved.'));
    }

    public function newToken(Outlet $outlet, PosTerminal $terminal, RegisterTerminal $register): RedirectResponse
    {
        Gate::authorize('update', $outlet);
        $token = $register->newToken($terminal);

        return $this->back($outlet, 'terminals', __('New device token for :name; the old one no longer works.', ['name' => $terminal->name]))
            ->with('terminal_token', ['id' => $terminal->id, 'token' => $token]);
    }

    /**
     * A (new) device token for the station's kitchen display, shown once.
     */
    public function displayToken(Outlet $outlet, KitchenStation $station, RegisterStationDisplay $register): RedirectResponse
    {
        Gate::authorize('update', $outlet);

        try {
            $token = $register->handle($station);
        } catch (RestaurantSetupInvalid $exception) {
            return $this->back($outlet, 'stations', null)->with('error', $exception->getMessage());
        }

        return $this->back($outlet, 'stations', __('Kitchen display token for :name; a screen with an older token is signed out.', ['name' => $station->name]))
            ->with('display_token', ['id' => $station->id, 'token' => $token]);
    }

    public function storeArea(DiningAreaRequest $request, Outlet $outlet, SaveDiningArea $save): RedirectResponse
    {
        $save->handle($outlet, null, (string) $request->validated('name'));

        return $this->back($outlet, 'floor', __('Area added.'));
    }

    public function updateArea(DiningAreaRequest $request, Outlet $outlet, DiningArea $area, SaveDiningArea $save): RedirectResponse
    {
        $save->handle($outlet, $area, (string) $request->validated('name'));

        return $this->back($outlet, 'floor', __('Area renamed.'));
    }

    public function destroyArea(Outlet $outlet, DiningArea $area, SaveDiningArea $save): RedirectResponse
    {
        Gate::authorize('editFloorPlan', $outlet);

        try {
            $save->delete($area);
        } catch (RestaurantSetupInvalid $exception) {
            return $this->back($outlet, 'floor', null)->with('error', $exception->getMessage());
        }

        return $this->back($outlet, 'floor', __('Area removed.'));
    }

    public function storeTable(DiningTableRequest $request, Outlet $outlet, SaveDiningTable $save): RedirectResponse
    {
        return $this->table($request, $outlet, null, $save, __('Table added.'));
    }

    public function updateTable(DiningTableRequest $request, Outlet $outlet, DiningTable $table, SaveDiningTable $save): RedirectResponse
    {
        return $this->table($request, $outlet, $table, $save, __('Table saved.'));
    }

    public function destroyTable(Outlet $outlet, DiningTable $table): RedirectResponse
    {
        Gate::authorize('editFloorPlan', $outlet);
        $table->delete();

        return $this->back($outlet, 'floor', __('Table removed.'));
    }

    public function positions(FloorPlanRequest $request, Outlet $outlet, DiningArea $area, SaveFloorPlan $save): JsonResponse
    {
        try {
            $moved = $save->handle($area, array_map(fn (array $position): array => ['id' => (int) $position['id'], 'x' => (float) $position['x'], 'y' => (float) $position['y']],
                (array) $request->validated('positions')));
        } catch (RestaurantSetupInvalid $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'ok' => true,
            'message' => trans_choice('Floor plan saved: :count table moved.|Floor plan saved: :count tables moved.', $moved),
            'tables' => $area->tables()->get(['id', 'pos_x', 'pos_y'])->map(fn (DiningTable $table): array => ['id' => $table->id, 'x' => $table->pos_x, 'y' => $table->pos_y])->all(),
        ]);
    }

    private function station(StationRequest $request, Outlet $outlet, ?KitchenStation $station, SaveStation $save): RedirectResponse
    {
        try {
            $save->handle($outlet, $station, [
                'name' => (string) $request->validated('name'), 'output' => (string) $request->validated('output'),
                'printer_id' => $request->filled('printer_id') ? (int) $request->validated('printer_id') : null,
                'sort_order' => (int) ($request->validated('sort_order') ?? $station->sort_order ?? $outlet->stations()->count()),
            ]);
        } catch (RestaurantSetupInvalid $exception) {
            return $this->back($outlet, 'stations', null)->withInput()->with('error', $exception->getMessage());
        }

        return $this->back($outlet, 'stations', __('Station saved.'));
    }

    private function table(DiningTableRequest $request, Outlet $outlet, ?DiningTable $table, SaveDiningTable $save, string $message): RedirectResponse
    {
        $area = DiningArea::query()->where('outlet_id', $outlet->id)->findOrFail((int) $request->validated('dining_area_id'));
        $save->handle($area, $table, (string) $request->validated('number'), (int) $request->validated('seats'), TableShape::from((string) $request->validated('shape')),
            ! $table instanceof DiningTable || $request->boolean('is_active'));

        return $this->back($outlet, 'floor', $message);
    }

    private function back(Outlet $outlet, string $tab, ?string $message): RedirectResponse
    {
        $redirect = redirect()->to(route('restaurant.outlets.show', $outlet).'#'.$tab);

        return $message !== null ? $redirect->with('success', $message) : $redirect;
    }
}
