<?php

namespace Modules\Billing\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\Billing\Actions\SaveGateway;
use Modules\Billing\Enums\GatewayMode;
use Modules\Billing\Enums\GatewayProvider;
use Modules\Billing\Http\Requests\SaveGatewayRequest;
use Modules\Billing\Models\OnlinePayment;
use Modules\Billing\Models\PaymentGateway;

/**
 * Setup → Online payments (Step 8.3): the current property's payment gateways and its recent online payments.
 */
class GatewayController extends Controller
{
    public function index(PropertyContext $context): View
    {
        $propertyId = $context->currentId() ?? abort(403, __('Choose a property first.'));
        $gateways = PaymentGateway::query()->where('property_id', $propertyId)->get()->keyBy(fn (PaymentGateway $gateway): string => $gateway->provider->value);

        return view('billing::gateways.index', [
            'providers' => array_values(array_filter(GatewayProvider::cases(), fn (GatewayProvider $provider): bool => $provider->isAvailable())),
            'gateways' => $gateways,
            'modes' => GatewayMode::options(),
            'payments' => OnlinePayment::query()->where('property_id', $propertyId)->latest('id')->limit(20)->get(),
            'subtitle' => $context->currentName(),
        ]);
    }

    public function update(SaveGatewayRequest $request, SaveGateway $save, PropertyContext $context): RedirectResponse
    {
        $provider = $request->provider();
        /** @var array<string, string|null> $credentials */
        $credentials = (array) $request->validated('credentials', []);
        $save->handle($context->currentId() ?? abort(403), $provider, GatewayMode::from((string) $request->validated('mode')), $credentials, (bool) $request->validated('is_enabled'));

        return to_route('billing.gateways.index')->with('success', __(':gateway saved.', ['gateway' => $provider->label()]));
    }
}
