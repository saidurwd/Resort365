<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Core\Contracts\TaxEngine;
use Modules\Core\DTOs\TaxCategorySummary;
use Modules\Rates\Actions\DeleteRatePlan;
use Modules\Rates\Actions\SaveRatePlan;
use Modules\Rates\Exceptions\RatePlanInUse;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\SaveRatePlanRequest;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\RatePlan;

class RatePlanController extends Controller
{
    use UsesCurrentProperty;

    public function __construct(private readonly TaxEngine $taxes) {}

    public function index(): View
    {
        Gate::authorize('viewAny', RatePlan::class);
        $propertyId = $this->currentPropertyId();

        return view('rates::rate-plans.index', [
            'propertyName' => $this->currentPropertyName(),
            'plans' => RatePlan::query()->where('property_id', $propertyId)->with(['depositPolicy', 'cancellationPolicy'])->withCount('rates')
                ->orderBy('sort_order')->orderBy('name')->get(),
            'defaultDeposit' => DepositPolicy::query()->where('property_id', $propertyId)->where('is_default', true)->value('name'),
            'defaultCancellation' => CancellationPolicy::query()->where('property_id', $propertyId)->where('is_default', true)->value('name'),
            'taxCategories' => $this->taxCategories(false),
            'currency' => $this->currency($propertyId),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', RatePlan::class);

        return view('rates::rate-plans.form', $this->formData(null, $this->currentPropertyId()));
    }

    public function store(SaveRatePlanRequest $request, SaveRatePlan $save): RedirectResponse
    {
        $plan = $save->handle(null, $request->validated());

        return to_route('rates.rate-plans.rates.edit', $plan)->with('success', __('Rate plan ":name" created. Now enter its rates.', ['name' => $plan->name]));
    }

    public function edit(RatePlan $ratePlan): View
    {
        Gate::authorize('update', $ratePlan);

        return view('rates::rate-plans.form', $this->formData($ratePlan, $ratePlan->property_id) + ['history' => app(AuditTrail::class)->for($ratePlan)]);
    }

    public function update(SaveRatePlanRequest $request, RatePlan $ratePlan, SaveRatePlan $save): RedirectResponse
    {
        $save->handle($ratePlan, $request->validated());

        return to_route('rates.rate-plans.index')->with('success', __('Rate plan ":name" saved.', ['name' => $ratePlan->name]));
    }

    public function destroy(RatePlan $ratePlan, DeleteRatePlan $delete): RedirectResponse
    {
        Gate::authorize('delete', $ratePlan);

        try {
            $delete->handle($ratePlan);
        } catch (RatePlanInUse $exception) {
            return to_route('rates.rate-plans.index')->with('error', $exception->getMessage());
        }

        return to_route('rates.rate-plans.index')->with('success', __('Rate plan ":name" deleted.', ['name' => $ratePlan->name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?RatePlan $plan, int $propertyId): array
    {
        return [
            'plan' => $plan,
            'taxCategories' => collect($this->taxCategories(! $plan instanceof RatePlan))
                ->mapWithKeys(fn (TaxCategorySummary $category): array => [$category->id => $category->name.($category->taxes !== [] ? ' ('.implode(' + ', $category->taxes).')' : '')])->all(),
            'currency' => $this->currency($propertyId),
            'depositPolicies' => DepositPolicy::query()->where('property_id', $propertyId)->orderBy('name')->get()
                ->mapWithKeys(fn (DepositPolicy $policy): array => [$policy->id => $policy->name.($policy->is_default ? ' ('.__('default').')' : '')])->all(),
            'cancellationPolicies' => CancellationPolicy::query()->where('property_id', $propertyId)->orderBy('name')->get()
                ->mapWithKeys(fn (CancellationPolicy $policy): array => [$policy->id => $policy->name.($policy->is_default ? ' ('.__('default').')' : '')])->all(),
            'history' => [],
        ];
    }

    /**
     * @return list<TaxCategorySummary>
     */
    private function taxCategories(bool $activeOnly): array
    {
        return $this->taxes->categories($activeOnly);
    }
}
