<?php

namespace Modules\Restaurant\Http\Controllers\Pos;

use App\Support\Cash\CashCount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\Settings;
use Modules\IAM\Contracts\UserDirectory;
use Modules\IAM\DTOs\UserSummary;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Restaurant\Actions\ClosePosSession;
use Modules\Restaurant\Actions\OpenPosSession;
use Modules\Restaurant\Enums\OrderStatus;
use Modules\Restaurant\Enums\PosSessionStatus;
use Modules\Restaurant\Exceptions\PosNotAllowed;
use Modules\Restaurant\Http\Requests\ClosePosSessionRequest;
use Modules\Restaurant\Http\Requests\ManagerApprovalRequest;
use Modules\Restaurant\Http\Requests\OpenPosSessionRequest;
use Modules\Restaurant\Models\ManagerApproval;
use Modules\Restaurant\Models\PosOrder;
use Modules\Restaurant\Models\PosSession;
use Modules\Restaurant\Services\ManagerApprovals;
use Modules\Restaurant\Services\PosContext;
use Modules\Restaurant\Services\SessionCash;

/**
 * The POS home on a terminal: its session (open with a float, X report, close with a cash count and,
 * for a large difference, a manager's PIN), the Z report, and the manager approval endpoint.
 */
class PosSessionController extends Controller
{
    public function main(PosContext $context, SessionCash $cash, CashCount $count, Settings $settings, ManagerApprovals $approvals, PropertyDirectory $properties): View
    {
        $terminal = $context->terminal();
        $session = PosSession::query()->where('pos_terminal_id', $terminal->id)->where('status', PosSessionStatus::Open->value)->first();
        [$received, $refunded] = $session instanceof PosSession ? $cash->cash($session) : ['0.00', '0.00'];

        return view('restaurant::pos.main', [
            'terminal' => $terminal,
            'session' => $session,
            'expected' => $session instanceof PosSession ? $count->expected($session->opening_float, $received, $refunded) : null,
            'denominations' => $count->denominations((string) $settings->get('billing.cash_denominations')),
            'varianceLimit' => (string) $settings->get('restaurant.session_variance_limit', $terminal->property_id),
            'approvers' => $approvals->approvers($terminal, ClosePosSession::APPROVAL, (int) auth()->id()),
            'recent' => PosSession::query()->where('pos_terminal_id', $terminal->id)->where('status', PosSessionStatus::Closed->value)->latest('closed_at')->limit(3)->get(),
            'currency' => $properties->find($terminal->property_id)->currencyCode ?? '',
            'canManage' => auth()->user()?->can('restaurant.session.manage') ?? false,
            'canTakeOrders' => auth()->user()?->can('restaurant.order.take') ?? false,
            'openOrders' => PosOrder::query()->where('outlet_id', $terminal->outlet_id)->where('status', OrderStatus::Open->value)->count(),
            'autoLockMinutes' => (int) $settings->get('restaurant.pos_auto_lock_minutes', $terminal->property_id),
        ]);
    }

    public function open(OpenPosSessionRequest $request, PosContext $context, OpenPosSession $open): RedirectResponse
    {
        try {
            $open->handle($context->terminal(), (int) $request->user()?->getAuthIdentifier(), (string) $request->validated('opening_float'));
        } catch (PosNotAllowed $exception) {
            return to_route('pos.main')->with('error', $exception->getMessage());
        }

        return to_route('pos.main')->with('success', __('Session opened.'));
    }

    public function close(ClosePosSessionRequest $request, PosContext $context, ClosePosSession $close): RedirectResponse
    {
        $session = PosSession::query()->where('pos_terminal_id', $context->terminal()->id)->where('status', PosSessionStatus::Open->value)->first();

        if (! $session instanceof PosSession) {
            return to_route('pos.main')->with('error', __('There is no open session on this terminal.'));
        }

        try {
            $closed = $close->handle($session, (array) $request->validated('count'), $request->validated('variance_reason'), (int) $request->user()?->getAuthIdentifier(),
                $request->filled('approval_id') ? (int) $request->validated('approval_id') : null);
        } catch (PosNotAllowed $exception) {
            return to_route('pos.main')->withInput()->with('error', $exception->getMessage());
        }

        return to_route('pos.sessions.report', $closed)->with('success', __('Session closed.'));
    }

    public function report(PosSession $session, PosContext $context, SessionCash $cash, CashCount $count, UserDirectory $users, PropertyDirectory $properties): View
    {
        abort_unless($session->pos_terminal_id === $context->terminal()->id || Gate::allows('restaurant.session.view'), 403);

        return static::reportView($session, $cash, $count, $users, $properties);
    }

    public function approve(ManagerApprovalRequest $request, PosContext $context, ManagerApprovals $approvals): JsonResponse
    {
        $action = (string) $request->validated('action');
        $definition = ManagerApprovals::ACTIONS[$action];

        try {
            $approval = $approvals->approve($context->terminal(), $action, $definition['permission'], (int) $request->validated('manager_id'), (string) $request->validated('pin'),
                (int) $request->user()?->getAuthIdentifier(), $definition['subject'], $request->filled('subject_id') ? (int) $request->validated('subject_id') : null,
                $request->validated('reason'));
        } catch (PosNotAllowed $exception) {
            return response()->json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'approval_id' => $approval->id, 'message' => __('Approved.')]);
    }

    /**
     * The X report (session still open) or Z report (closed), on 80 mm paper.
     */
    public static function reportView(PosSession $session, SessionCash $cash, CashCount $count, UserDirectory $users, PropertyDirectory $properties): View
    {
        $session->loadMissing(['outlet', 'terminal']);
        [$received, $refunded] = $session->isOpen() ? $cash->cash($session) : [(string) $session->cash_received, (string) $session->cash_refunded];
        $names = collect($users->all())->mapWithKeys(fn (UserSummary $user): array => [$user->id => $user->name]);

        return view('restaurant::pos.report', [
            'session' => $session,
            'received' => $received,
            'refunded' => $refunded,
            'expected' => $session->expected_cash ?? $count->expected($session->opening_float, $received, $refunded),
            'property' => $properties->find($session->property_id),
            'openedBy' => $names[$session->opened_by] ?? '',
            'closedBy' => $session->closed_by !== null ? ($names[$session->closed_by] ?? '') : null,
            'approvedBy' => $session->manager_approval_id !== null ? ($names[ManagerApproval::query()->whereKey($session->manager_approval_id)->value('approved_by')] ?? '') : null,
        ]);
    }
}
