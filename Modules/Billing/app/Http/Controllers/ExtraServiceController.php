<?php

namespace Modules\Billing\Http\Controllers;

use App\Support\Tenancy\PropertyContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Billing\Actions\SaveExtraService;
use Modules\Billing\Http\Requests\SaveExtraServiceRequest;
use Modules\Billing\Models\ChargeCode;
use Modules\Billing\Models\ExtraService;

/**
 * Setup → Extras: the current property's catalogue of extras posted to folios.
 */
class ExtraServiceController extends Controller
{
    public function __construct(private readonly PropertyContext $properties) {}

    public function index(): View
    {
        Gate::authorize('viewAny', ExtraService::class);

        return view('billing::extra-services.index', [
            'services' => ExtraService::query()->with('chargeCode')->where('property_id', $this->properties->currentId())->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', ExtraService::class);

        return view('billing::extra-services.form', ['service' => null, 'codes' => $this->codes()]);
    }

    public function store(SaveExtraServiceRequest $request, SaveExtraService $save): RedirectResponse
    {
        $service = $save->handle(null, [...$request->validated(), 'property_id' => $this->properties->currentId() ?? abort(403, __('Choose a property first.'))]);

        return to_route('billing.extra-services.index')->with('success', __('Extra ":name" created.', ['name' => $service->name]));
    }

    public function edit(ExtraService $extraService): View
    {
        Gate::authorize('update', $extraService);

        return view('billing::extra-services.form', ['service' => $extraService, 'codes' => $this->codes()]);
    }

    public function update(SaveExtraServiceRequest $request, ExtraService $extraService, SaveExtraService $save): RedirectResponse
    {
        $save->handle($extraService, $request->validated());

        return to_route('billing.extra-services.index')->with('success', __('Extra ":name" saved.', ['name' => $extraService->name]));
    }

    public function destroy(ExtraService $extraService): RedirectResponse
    {
        Gate::authorize('delete', $extraService);
        $extraService->delete();

        return to_route('billing.extra-services.index')->with('success', __('Extra ":name" deleted.', ['name' => $extraService->name]));
    }

    /**
     * @return array<int, string>
     */
    private function codes(): array
    {
        return ChargeCode::query()->where('is_active', true)->orderBy('sort_order')->get()
            ->mapWithKeys(fn (ChargeCode $code): array => [$code->id => $code->code.' — '.$code->name])->all();
    }
}
