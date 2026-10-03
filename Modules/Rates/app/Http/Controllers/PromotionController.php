<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Property\Contracts\InventoryCatalog;
use Modules\Rates\Actions\DeletePromotion;
use Modules\Rates\Actions\SavePromotion;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\SavePromotionRequest;
use Modules\Rates\Models\Promotion;
use Modules\Rates\Models\RatePlan;

class PromotionController extends Controller
{
    use UsesCurrentProperty;

    public function __construct(private readonly InventoryCatalog $catalog) {}

    public function index(): View
    {
        Gate::authorize('viewAny', Promotion::class);
        $propertyId = $this->currentPropertyId();

        return view('rates::promotions.index', [
            'propertyName' => $this->currentPropertyName(),
            'promotions' => Promotion::query()->where('property_id', $propertyId)->orderByDesc('is_active')->orderBy('name')->get(),
            'planNames' => RatePlan::query()->where('property_id', $propertyId)->pluck('name', 'id')->all(),
            'unitNames' => $this->unitOptions($propertyId),
            'currency' => $this->currency($propertyId),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Promotion::class);

        return view('rates::promotions.form', $this->formData(null, $this->currentPropertyId()));
    }

    public function store(SavePromotionRequest $request, SavePromotion $save): RedirectResponse
    {
        $promotion = $save->handle(null, $request->promotionData());

        return to_route('rates.promotions.index')->with('success', __('Promotion ":name" created.', ['name' => $promotion->name]));
    }

    public function edit(Promotion $promotion): View
    {
        Gate::authorize('update', $promotion);

        return view('rates::promotions.form', $this->formData($promotion, $promotion->property_id) + ['history' => app(AuditTrail::class)->for($promotion)]);
    }

    public function update(SavePromotionRequest $request, Promotion $promotion, SavePromotion $save): RedirectResponse
    {
        $save->handle($promotion, $request->promotionData());

        return to_route('rates.promotions.index')->with('success', __('Promotion ":name" saved.', ['name' => $promotion->name]));
    }

    public function destroy(Promotion $promotion, DeletePromotion $delete): RedirectResponse
    {
        Gate::authorize('delete', $promotion);
        $delete->handle($promotion);

        return to_route('rates.promotions.index')->with('success', __('Promotion ":name" deleted.', ['name' => $promotion->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Promotion $promotion, int $propertyId): array
    {
        return [
            'promotion' => $promotion,
            'plans' => RatePlan::query()->where('property_id', $propertyId)->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all(),
            'units' => $this->unitOptions($propertyId),
            'currency' => $this->currency($propertyId),
            'history' => [],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function unitOptions(int $propertyId): array
    {
        $options = [];

        foreach ($this->catalog->unitTypes($propertyId) as $unit) {
            $options[$unit->key()] = $unit->name;
        }

        return $options;
    }
}
