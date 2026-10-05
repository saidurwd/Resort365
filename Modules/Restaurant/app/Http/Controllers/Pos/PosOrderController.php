<?php

namespace Modules\Restaurant\Http\Controllers\Pos;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\Settings;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Actions\AddOrderLine;
use Modules\Restaurant\Actions\ChangePendingLine;
use Modules\Restaurant\Actions\MoveOrder;
use Modules\Restaurant\Actions\OpenOrder;
use Modules\Restaurant\Actions\SendOrder;
use Modules\Restaurant\Actions\VoidOrderLine;
use Modules\Restaurant\Enums\Course;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\OrderType;
use Modules\Restaurant\Enums\VoidReason;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Http\Requests\ChangeOrderLineRequest;
use Modules\Restaurant\Http\Requests\OpenOrderRequest;
use Modules\Restaurant\Http\Requests\OrderActionRequest;
use Modules\Restaurant\Http\Requests\OrderLineRequest;
use Modules\Restaurant\Http\Requests\VoidOrderLineRequest;
use Modules\Restaurant\Models\DiningArea;
use Modules\Restaurant\Models\DiningTable;
use Modules\Restaurant\Models\Kot;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosOrderLine;
use Modules\Restaurant\Services\ManagerApprovals;
use Modules\Restaurant\Services\MenuCatalog;
use Modules\Restaurant\Services\OrderPresenter;
use Modules\Restaurant\Services\PosContext;

/**
 * Taking orders on the POS (ARCHITECTURE §5.10.3–5.10.6, §12 rule 15): the floor (tables with their
 * open orders, takeaway), the order screen, and the JSON endpoints it calls. Each endpoint runs the
 * same Action the server would anyway and answers with the whole order, totals from the server.
 */
class PosOrderController extends Controller
{
    public function floor(PosContext $context, Settings $settings, PropertyDirectory $properties, UserDirectory $users): View
    {
        $terminal = $context->terminal();
        $orders = PosOrder::query()->where('outlet_id', $terminal->outlet_id)->where('status', OrderStatus::Open->value)->with('table')->orderBy('opened_at')->get();
        $names = $this->names($users);

        return view('restaurant::pos.floor', [
            'terminal' => $terminal,
            'areas' => DiningArea::query()->where('outlet_id', $terminal->outlet_id)->with(['tables' => fn ($query) => $query->where('is_active', true)->orderBy('number')])->orderBy('sort_order')->get(),
            'byTable' => $orders->whereNotNull('dining_table_id')->keyBy('dining_table_id'),
            'takeaway' => $orders->whereNull('dining_table_id')->values(),
            'names' => $names,
            'currency' => $properties->find($terminal->property_id)->currencyCode ?? '',
            'businessDate' => $properties->find($terminal->property_id)->businessDate ?? null,
            'canTake' => auth()->user()?->can('restaurant.order.take') ?? false,
            'autoLockMinutes' => (int) $settings->get('restaurant.pos_auto_lock_minutes', $terminal->property_id),
        ]);
    }

    public function open(OpenOrderRequest $request, PosContext $context, OpenOrder $open): RedirectResponse
    {
        try {
            $order = $open->handle($context->outlet(), OrderType::from((string) $request->validated('type')), (int) $request->user()?->getAuthIdentifier(),
                $request->filled('table_id') ? (int) $request->validated('table_id') : null, (int) ($request->validated('covers') ?? 1));
        } catch (PosNotAllowed $exception) {
            return to_route('pos.floor')->with('error', $exception->getMessage());
        }

        return to_route('pos.orders.show', $order);
    }

    public function show(PosOrder $order, PosContext $context, MenuCatalog $catalog, OrderPresenter $presenter, ManagerApprovals $approvals, Settings $settings, PropertyDirectory $properties): View|RedirectResponse
    {
        $this->authorizeOrder($order, $context);

        if (! $order->isOpen()) {
            return to_route('pos.floor')->with('error', __('Order :number is closed.', ['number' => $order->order_no]));
        }

        $terminal = $context->terminal();
        $user = auth()->user();
        $busy = PosOrder::query()->where('outlet_id', $terminal->outlet_id)->where('status', OrderStatus::Open->value)->whereNotNull('dining_table_id')->pluck('dining_table_id');

        return view('restaurant::pos.order', [
            'terminal' => $terminal,
            'order' => $order,
            'state' => [
                'order' => $presenter->present($order),
                'menu' => $catalog->forOutlet($terminal->outlet),
                'courses' => array_map(fn (Course $course): array => ['value' => $course->value, 'label' => $course->label()], Course::cases()),
                'reasons' => array_map(fn (VoidReason $reason): array => ['value' => $reason->value, 'label' => $reason->label()], VoidReason::cases()),
                'freeTables' => DiningTable::query()->where('outlet_id', $terminal->outlet_id)->where('is_active', true)->whereNotIn('id', $busy)->orderBy('number')
                    ->get(['id', 'number'])->map(fn (DiningTable $table): array => ['id' => $table->id, 'number' => $table->number])->values()->all(),
                'otherOrders' => PosOrder::query()->where('outlet_id', $terminal->outlet_id)->where('status', OrderStatus::Open->value)->whereKeyNot($order->id)->with('table')->orderBy('order_no')->get()
                    ->map(fn (PosOrder $other): array => ['id' => $other->id, 'label' => $other->order_no.($other->table ? ' · '.__('Table :number', ['number' => $other->table->number]) : ' · '.__('Takeaway'))])->values()->all(),
                'mayVoid' => $user?->can('restaurant.order.void') ?? false,
                'mayPriceOpenItems' => $user?->can('restaurant.order.open-item') ?? false,
                'urls' => [
                    'base' => route('pos.orders.lines.store', $order),
                    'send' => route('pos.orders.send', $order),
                    'transfer' => route('pos.orders.transfer', $order),
                    'merge' => route('pos.orders.merge', $order),
                    'cancel' => route('pos.orders.cancel', $order),
                    'approve' => route('pos.approvals.store'),
                    'floor' => route('pos.floor'),
                ],
                'messages' => ['offline' => __('No connection. Try again.'), 'cancel' => __('Cancel this order?'), 'cancelYes' => __('Cancel order'), 'keep' => __('Keep it'),
                    'soldOut' => __(':item is sold out.'), 'notServed' => __(':item is not served at this time.')],
            ],
            'approvers' => $approvals->approvers($terminal, VoidOrderLine::APPROVAL, (int) auth()->id()),
            'currency' => $properties->find($terminal->property_id)->currencyCode ?? '',
            'autoLockMinutes' => (int) $settings->get('restaurant.pos_auto_lock_minutes', $terminal->property_id),
        ]);
    }

    public function data(PosOrder $order, PosContext $context, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        return response()->json(['ok' => true, 'order' => $presenter->present($order)]);
    }

    public function addLine(OrderLineRequest $request, PosOrder $order, PosContext $context, AddOrderLine $add, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        return $this->respond($presenter, $order, fn (): PosOrderLine => $add->handle($order, $request->validated(), (int) $request->user()?->getAuthIdentifier(),
            $request->user()?->can('restaurant.order.open-item') ?? false));
    }

    public function changeLine(ChangeOrderLineRequest $request, PosOrder $order, PosOrderLine $line, PosContext $context, ChangePendingLine $change, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        return $this->respond($presenter, $order, fn (): PosOrderLine => $change->handle($line, $request->validated()));
    }

    public function removeLine(Request $request, PosOrder $order, PosOrderLine $line, PosContext $context, ChangePendingLine $change, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        return $this->respond($presenter, $order, fn () => $change->remove($line));
    }

    public function voidLine(VoidOrderLineRequest $request, PosOrder $order, PosOrderLine $line, PosContext $context, VoidOrderLine $void, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);
        $kots = [];

        return $this->respond($presenter, $order, function () use ($request, $line, $void, &$kots): void {
            $kot = $void->handle($line, VoidReason::from((string) $request->validated('reason')), $request->validated('note'), $request->boolean('wastage'),
                (int) $request->user()?->getAuthIdentifier(), $request->user()?->can('restaurant.order.void') ?? false,
                $request->filled('approval_id') ? (int) $request->validated('approval_id') : null);
            $kots = [$kot];
        }, $kots, __('Voided; the kitchen is told.'));
    }

    public function send(OrderActionRequest $request, PosOrder $order, PosContext $context, SendOrder $send, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);
        $kots = [];

        return $this->respond($presenter, $order, function () use ($request, $order, $send, &$kots): void {
            $kots = $send->handle($order, (int) $request->user()?->getAuthIdentifier(), $request->filled('course') ? Course::from((string) $request->validated('course')) : null);
        }, $kots);
    }

    public function transfer(OrderActionRequest $request, PosOrder $order, PosContext $context, MoveOrder $move, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        return $this->respond($presenter, $order, fn (): PosOrder => $move->transfer($order, (int) $request->validated('table_id')));
    }

    public function merge(OrderActionRequest $request, PosOrder $order, PosContext $context, MoveOrder $move, OrderPresenter $presenter): JsonResponse
    {
        $this->authorizeOrder($order, $context);

        return $this->respond($presenter, $order, fn (): PosOrder => $move->merge($order, (int) $request->validated('order_id')));
    }

    public function cancel(OrderActionRequest $request, PosOrder $order, PosContext $context, MoveOrder $move): JsonResponse|RedirectResponse
    {
        $this->authorizeOrder($order, $context);

        try {
            $move->cancel($order);
        } catch (PosNotAllowed $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'redirect' => route('pos.floor'), 'message' => __('Order :number cancelled.', ['number' => $order->order_no])]);
    }

    /**
     * A kitchen ticket on 80 mm paper; the first print is stamped.
     */
    public function printKot(Kot $kot, PosContext $context, UserDirectory $users): View
    {
        $order = $kot->order;
        $this->authorizeOrder($order, $context);
        $kot->loadMissing(['lines.line', 'station']);

        if ($kot->printed_at === null) {
            $kot->forceFill(['printed_at' => now()])->save();
        }

        return view('restaurant::pos.kot', ['kot' => $kot, 'order' => $order->loadMissing(['table', 'outlet']), 'waiter' => $this->names($users)[$order->waiter_id] ?? '']);
    }

    /**
     * Runs an order Action and answers with the order, or with the reason it was refused.
     *
     * @param  list<Kot>  $kots  tickets made by the action (filled in by reference)
     */
    private function respond(OrderPresenter $presenter, PosOrder $order, callable $action, array &$kots = [], ?string $message = null): JsonResponse
    {
        try {
            $action();
        } catch (PosNotAllowed $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        $fresh = PosOrder::query()->findOrFail($order->id);
        $print = array_values(array_map(fn (Kot $kot): int => $kot->id, array_filter($kots, fn (Kot $kot): bool => $kot->station === null || $kot->station->output->needsPrinter())));

        return response()->json(['ok' => true, 'order' => $presenter->present($fresh, $print), 'message' => $message ?? ($kots !== [] ? trans_choice(':count kitchen ticket sent.|:count kitchen tickets sent.', count($kots)) : null)]);
    }

    private function authorizeOrder(PosOrder $order, PosContext $context): void
    {
        abort_unless($order->outlet_id === $context->terminal()->outlet_id, 404);
        Gate::authorize('update', $order);
    }

    /**
     * @return array<int, string>
     */
    private function names(UserDirectory $users): array
    {
        return collect($users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name])->all();
    }
}
