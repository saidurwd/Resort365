<?php

namespace Modules\Restaurant\Http\Controllers\Kds;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Core\Contracts\Settings;
use Modules\Restaurant\Actions\ProgressKot;
use Modules\Restaurant\Broadcasting\RestaurantChannels;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Http\Middleware\EnsureKdsStation;
use Modules\Restaurant\Http\Requests\KdsProgressRequest;
use Modules\Restaurant\Http\Requests\RegisterDeviceRequest;
use Modules\Restaurant\Models\KitchenStation;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\Outlet;
use Modules\Restaurant\Services\KdsBoard;
use Modules\Restaurant\Services\KdsContext;
use Modules\Restaurant\Services\KdsDevice;
use Modules\Restaurant\Services\OutletAccess;

/**
 * The kitchen display (ARCHITECTURE §10.1, §10.3): registering a station's screen with its display token,
 * the station picker for people signed in to the app, the ticket board and its JSON, and the taps
 * (start, ready, bump, recall). The board listens to the station's private channel and polls when the
 * WebSocket is down.
 */
class KdsController extends Controller
{
    public function register(Request $request, KdsDevice $device): View|RedirectResponse
    {
        return $device->fromRequest($request) instanceof KitchenStation ? to_route('kds.board') : view('restaurant::kds.register');
    }

    public function storeDevice(RegisterDeviceRequest $request, KdsDevice $device): RedirectResponse
    {
        $station = $device->stationForToken((string) $request->validated('token'));

        if (! $station instanceof KitchenStation) {
            return to_route('kds.register')->withErrors(['token' => __('No kitchen display has this token.')]);
        }

        return to_route('kds.board')->withCookie($device->cookieFor($station))
            ->with('success', __('This screen now shows :station at :outlet.', ['station' => $station->name, 'outlet' => $station->outlet->name]));
    }

    /**
     * People signed in to the app pick a station of an outlet they work in.
     */
    public function stations(Request $request, OutletAccess $access): View
    {
        $ids = $access->outletIds((int) $request->user()?->getAuthIdentifier());
        $outlets = Outlet::query()->where('is_active', true)->when($ids !== null, fn ($query) => $query->whereIn('id', $ids))
            ->with(['stations' => fn ($query) => $query->orderBy('sort_order')])->orderBy('sort_order')->get();

        return view('restaurant::kds.stations', ['outlets' => $outlets]);
    }

    public function board(KdsContext $context, Settings $settings): View
    {
        $station = $context->station()->loadMissing('outlet');

        return view('restaurant::kds.board', [
            'station' => $station,
            'isDevice' => $context->isDevice(),
            'state' => [
                'board' => $this->boardFor($context, $settings),
                'channel' => RestaurantChannels::kitchen($station->tenant_id, $station->id),
                'urls' => ['board' => route('kds.api.board'), 'progress' => route('kds.api.progress', ['kot' => '__KOT__'])],
                'labels' => [
                    'start' => __('Start'), 'ready' => __('Ready'), 'bump' => __('Bump'), 'ack' => __('Seen'), 'recall' => __('Recall'),
                    'void' => __('VOID'), 'seat' => __('Seat'), 'min' => __('min'), 'offline' => __('No connection. Try again.'),
                ],
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function boardFor(KdsContext $context, Settings $settings): array
    {
        $station = $context->station();

        return app(KdsBoard::class)->for($station, (int) $settings->get('restaurant.kds_warn_minutes', $station->property_id),
            (int) $settings->get('restaurant.kds_late_minutes', $station->property_id));
    }

    public function boardData(KdsContext $context, Settings $settings): JsonResponse
    {
        return response()->json(['ok' => true, 'board' => $this->boardFor($context, $settings)]);
    }

    public function progress(KdsProgressRequest $request, Kot $kot, KdsContext $context, ProgressKot $progress, Settings $settings): JsonResponse
    {
        abort_unless($kot->kitchen_station_id === $context->station()->id, 404);

        try {
            $progress->handle($kot, (string) $request->validated('action'));
        } catch (PosNotAllowed $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage(), 'board' => $this->boardFor($context, $settings)], 422);
        }

        return response()->json(['ok' => true, 'board' => $this->boardFor($context, $settings)]);
    }

    /**
     * Signs this screen out of its station (a device forgets its token; a person picks another station).
     */
    public function signOut(Request $request, KdsDevice $device, KdsContext $context): RedirectResponse
    {
        $request->session()->forget(EnsureKdsStation::SESSION);

        return $context->isDevice() ? to_route('kds.register')->withCookie($device->forget()) : to_route('kds.stations');
    }
}
