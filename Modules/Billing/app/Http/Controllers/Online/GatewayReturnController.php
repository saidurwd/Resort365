<?php

namespace Modules\Billing\Http\Controllers\Online;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Billing\Actions\CompleteOnlinePayment;
use Modules\Billing\DTOs\GatewayNotice;
use Modules\Billing\DTOs\GatewayResult;
use Modules\Billing\Enums\GatewayOutcome;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Enums\OnlinePaymentStatus;
use Modules\Billing\Exceptions\GatewayError;
use Modules\Billing\Models\OnlinePayment;
use Modules\Billing\Models\PaymentGateway;
use Modules\Billing\Services\GatewayLog;
use Modules\Billing\Services\Gateways\GatewayDrivers;
use Modules\Billing\Services\Gateways\TestGateway;

/**
 * Where gateways send guests back and send their notifications (Step 8.3). Neither is trusted alone: the adapter asks the gateway
 * (or checks the notification's signature and then asks), and CompleteOnlinePayment settles the attempt once. These routes run
 * without the session (a gateway's cross-site POST must not replace the guest's session) or CSRF checks.
 */
class GatewayReturnController extends Controller
{
    public function __construct(private readonly GatewayDrivers $drivers, private readonly CompleteOnlinePayment $complete, private readonly GatewayLog $log) {}

    /**
     * The guest is back from the gateway: confirm, settle, and send them to the page that started the payment with `?payment=`.
     */
    public function back(Request $request, string $uuid): RedirectResponse
    {
        $attempt = OnlinePayment::query()->with('gateway')->where('uuid', $uuid)->firstOrFail();

        try {
            $result = $this->drivers->for($attempt->provider)->confirm($attempt->gateway->setup(), $attempt->uuid, $attempt->gateway_ref, $request->all());
        } catch (GatewayError $error) {
            $result = new GatewayResult(GatewayOutcome::Pending, message: $error->getMessage());
        }

        $this->log->record($attempt->property_id, $attempt->provider, 'return', $attempt, ['outcome' => $request->input('outcome'), 'message' => $result->message, ...$result->raw], outcome: $result->outcome->value);
        $attempt = $this->complete->handle($attempt, $result);

        $status = match ($attempt->status) {
            OnlinePaymentStatus::Paid => 'paid',
            OnlinePaymentStatus::Pending => 'pending',
            OnlinePaymentStatus::Unapplied => 'review',
            default => 'failed',
        };

        return redirect()->to(GatewayDrivers::withQuery($attempt->return_url ?: url('/'), ['payment' => $status]));
    }

    /**
     * A notification (IPN, webhook): a bad signature is refused and logged; a replay is acknowledged and ignored.
     */
    public function notify(Request $request, string $provider, int $gateway): Response
    {
        $config = PaymentGateway::query()->where('provider', $provider)->findOrFail($gateway);
        $notice = $this->drivers->for($config->provider)->notice($config->setup(), $request);

        if (! $notice instanceof GatewayNotice) {
            $this->log->record($config->property_id, $config->provider, 'notification', null, ['ip' => $request->ip()], signatureValid: false, outcome: 'rejected');

            return response('Invalid notification.', 400);
        }

        $attempt = $notice->reference !== null ? OnlinePayment::query()->with('gateway')->where('payment_gateway_id', $config->id)->where('uuid', $notice->reference)->first() : null;

        if (! $this->log->record($config->property_id, $config->provider, 'notification', $attempt, $notice->raw, $notice->eventId, true, $attempt instanceof OnlinePayment ? 'received' : 'unknown')) {
            return response('Already processed.');
        }

        if ($attempt instanceof OnlinePayment) {
            try {
                $result = $this->drivers->for($attempt->provider)->confirm($attempt->gateway->setup(), $attempt->uuid, $attempt->gateway_ref, $notice->input);
                $this->complete->handle($attempt, $result);
            } catch (GatewayError $error) {
                $this->log->record($config->property_id, $config->provider, 'confirm', $attempt, ['error' => $error->getMessage()], outcome: 'failed');

                return response('Could not confirm.', 503);
            }
        }

        return response('OK');
    }

    /**
     * The test gateway's "hosted page" (local and staging only): Pay, Fail or Cancel.
     */
    public function testPage(string $uuid): View
    {
        $attempt = OnlinePayment::query()->where('uuid', $uuid)->where('provider', GatewayProvider::Test->value)->firstOrFail();
        abort_if(app()->isProduction(), 404);

        return view('billing::online.test-gateway', ['attempt' => $attempt]);
    }

    /**
     * The test gateway sends the guest back, signed like a real gateway would.
     */
    public function testSubmit(Request $request, string $uuid): RedirectResponse
    {
        $attempt = OnlinePayment::query()->where('uuid', $uuid)->where('provider', GatewayProvider::Test->value)->firstOrFail();
        abort_if(app()->isProduction(), 404);
        $outcome = in_array($request->input('outcome'), ['paid', 'failed', 'cancelled'], true) ? (string) $request->input('outcome') : 'failed';
        $transaction = $outcome === 'paid' ? 'TEST-TXN-'.strtoupper(substr(md5($uuid), 0, 10)) : '';

        return redirect()->to(GatewayDrivers::withQuery(route('billing.online.return', ['uuid' => $uuid]), [
            'outcome' => $outcome, 'amount' => $attempt->amount, 'currency' => $attempt->currency_code, 'txn' => $transaction,
            'sig' => TestGateway::sign($uuid, $outcome, $attempt->amount, $transaction),
        ]));
    }
}
