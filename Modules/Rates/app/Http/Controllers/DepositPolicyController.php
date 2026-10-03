<?php

namespace Modules\Rates\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Core\Contracts\AuditTrail;
use Modules\Rates\Actions\DeleteDepositPolicy;
use Modules\Rates\Actions\SaveDepositPolicy;
use Modules\Rates\Exceptions\PolicyInUse;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\SaveDepositPolicyRequest;
use Modules\Rates\Models\DepositPolicy;

class DepositPolicyController extends Controller
{
    use UsesCurrentProperty;

    public function create(): View
    {
        Gate::authorize('create', DepositPolicy::class);

        return view('rates::policies.deposit-form', ['policy' => null, 'history' => [], 'currency' => $this->currency($this->currentPropertyId())]);
    }

    public function store(SaveDepositPolicyRequest $request, SaveDepositPolicy $save): RedirectResponse
    {
        $policy = $save->handle(null, $request->validated());

        return to_route('rates.policies.index')->with('success', __('Deposit policy ":name" created.', ['name' => $policy->name]));
    }

    public function edit(DepositPolicy $depositPolicy): View
    {
        Gate::authorize('update', $depositPolicy);

        return view('rates::policies.deposit-form', [
            'policy' => $depositPolicy, 'history' => app(AuditTrail::class)->for($depositPolicy), 'currency' => $this->currency($depositPolicy->property_id),
        ]);
    }

    public function update(SaveDepositPolicyRequest $request, DepositPolicy $depositPolicy, SaveDepositPolicy $save): RedirectResponse
    {
        $save->handle($depositPolicy, $request->validated());

        return to_route('rates.policies.index')->with('success', __('Deposit policy ":name" saved.', ['name' => $depositPolicy->name]));
    }

    public function destroy(DepositPolicy $depositPolicy, DeleteDepositPolicy $delete): RedirectResponse
    {
        Gate::authorize('delete', $depositPolicy);

        try {
            $delete->handle($depositPolicy);
        } catch (PolicyInUse $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return to_route('rates.policies.index')->with('success', __('Deposit policy ":name" deleted.', ['name' => $depositPolicy->name]));
    }
}
