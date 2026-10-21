<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Modules\Core\Contracts\PlanLimits;
use Modules\Core\Contracts\SubscriptionAccount;
use Modules\Core\Contracts\TenantDataExports;
use Modules\Core\Contracts\UsageMeters;
use Modules\Core\Http\Requests\CancelSubscriptionRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Settings → Subscription (Step 7.4): the company's plan, trial or paid period, usage against the plan, the platform's invoices,
 * cancelling, and the export of all its data. Authorized by the route permissions.
 */
class SubscriptionController extends Controller
{
    /** Usage meter => the plan limit that caps it. */
    private const array LIMITS = ['properties' => 'max_properties', 'rooms' => 'max_rooms', 'users' => 'max_users', 'storage' => 'storage_gb'];

    public function show(Request $request, SubscriptionAccount $account, UsageMeters $meters, PlanLimits $limits, TenantDataExports $exports): View
    {
        $usage = [];

        foreach ($meters->all() as $name => $meter) {
            $usage[] = ['label' => __($meter['label']), 'unit' => $meter['unit'], 'used' => $meters->measure($name), 'limit' => isset(self::LIMITS[$name]) ? $limits->limit(self::LIMITS[$name]) : null];
        }

        return view('core::subscription.show', [
            'subscription' => $account->current(), 'invoices' => $account->invoices(), 'usage' => $usage,
            'exports' => $request->user()?->can('core.data-export.manage') ? $exports->recent() : [],
        ]);
    }

    public function invoice(int $invoice, SubscriptionAccount $account): Response
    {
        $pdf = $account->invoicePdf($invoice) ?? abort(404);

        return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="invoice-'.$invoice.'.pdf"']);
    }

    public function cancel(CancelSubscriptionRequest $request, SubscriptionAccount $account): RedirectResponse
    {
        $account->cancel((string) $request->validated('reason'));

        return to_route('core.subscription.show')->with('success', __('Your subscription is cancelled. You can keep working until the date shown, and take your subscription back until then.'));
    }

    public function resume(SubscriptionAccount $account): RedirectResponse
    {
        $account->resume();

        return to_route('core.subscription.show')->with('success', __('Your subscription continues.'));
    }

    public function export(Request $request, TenantDataExports $exports): RedirectResponse
    {
        $user = $request->user();
        $exports->request($user?->getAuthIdentifier() !== null ? (int) $user->getAuthIdentifier() : null, (string) $user?->getAttribute('name'));

        return to_route('core.subscription.show', ['#data'])->with('success', __('Your data export is being prepared. You will be told when it is ready.'));
    }

    public function download(int $export, TenantDataExports $exports): StreamedResponse
    {
        $file = $exports->file($export) ?? abort(404);

        return Storage::disk($file['disk'])->download($file['path'], $file['name']);
    }
}
