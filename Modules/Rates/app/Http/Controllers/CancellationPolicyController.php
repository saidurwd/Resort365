<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Rates\Actions\DeleteCancellationPolicy;
use Modules\Rates\Actions\SaveCancellationPolicy;
use Modules\Rates\Exceptions\PolicyInUse;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\SaveCancellationPolicyRequest;
use Modules\Rates\Models\CancellationPolicy;

class CancellationPolicyController extends Controller
{
    use UsesCurrentProperty;

    public function create(): View
    {
        Gate::authorize('create', CancellationPolicy::class);

        return view('rates::policies.cancellation-form', ['policy' => null, 'history' => [], 'currency' => $this->currency($this->currentPropertyId())]);
    }

    public function store(SaveCancellationPolicyRequest $request, SaveCancellationPolicy $save): RedirectResponse
    {
        $policy = $save->handle(null, $request->validated());

        return to_route('rates.policies.index')->with('success', __('Cancellation policy ":name" created.', ['name' => $policy->name]));
    }

    public function edit(CancellationPolicy $cancellationPolicy): View
    {
        Gate::authorize('update', $cancellationPolicy);

        return view('rates::policies.cancellation-form', [
            'policy' => $cancellationPolicy->load('rules'), 'history' => app(AuditTrail::class)->for($cancellationPolicy), 'currency' => $this->currency($cancellationPolicy->property_id),
        ]);
    }

    public function update(SaveCancellationPolicyRequest $request, CancellationPolicy $cancellationPolicy, SaveCancellationPolicy $save): RedirectResponse
    {
        $save->handle($cancellationPolicy, $request->validated());

        return to_route('rates.policies.index')->with('success', __('Cancellation policy ":name" saved.', ['name' => $cancellationPolicy->name]));
    }

    public function destroy(CancellationPolicy $cancellationPolicy, DeleteCancellationPolicy $delete): RedirectResponse
    {
        Gate::authorize('delete', $cancellationPolicy);

        try {
            $delete->handle($cancellationPolicy);
        } catch (PolicyInUse $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('rates.policies.index')->with('success', __('Cancellation policy ":name" deleted.', ['name' => $cancellationPolicy->name]));
    }
}
