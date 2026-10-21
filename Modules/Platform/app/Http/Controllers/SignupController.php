<?php

namespace Modules\Platform\Http\Controllers;

use App\Actions\Tenancy\CreateTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Modules\Platform\Actions\RegisterTenant;
use Modules\Platform\Enums\BillingPeriod;
use Modules\Platform\Http\Requests\SignupRequest;
use Modules\Platform\Models\Plan;

/**
 * Public sign-up on the central domain (Step 7.4): the form with the plans, the live check of the address, and the sign-up itself,
 * which sends the owner to their new subdomain.
 */
class SignupController extends Controller
{
    public function create(Request $request): View
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();
        $chosen = $plans->firstWhere('code', $request->query('plan')) ?? $plans->firstWhere('code', 'professional') ?? $plans->first();

        return view('platform::signup.create', [
            'plans' => $plans, 'chosen' => $chosen, 'periods' => BillingPeriod::options(), 'trialDays' => (int) config('tenancy.trial_days'), 'domain' => config('tenancy.central_domain'),
        ]);
    }

    public function store(SignupRequest $request, RegisterTenant $register): RedirectResponse
    {
        $plan = Plan::query()->findOrFail((int) $request->validated('plan_id'));
        /** @var array{company: string, slug: string, name: string, email: string, password: string} $data */
        $data = $request->safe()->only(['company', 'slug', 'name', 'email', 'password']);
        $result = $register->handle($data, $plan, BillingPeriod::from((string) $request->validated('billing_period')));

        return redirect()->away($result['link']);
    }

    /** Whether a subdomain is free, for the form's live check. */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make(['slug' => strtolower(trim((string) $request->query('slug')))], ['slug' => CreateTenant::rules()['slug']],
            ['slug.not_in' => __('That address is reserved.'), 'slug.unique' => __('That address is taken. Try another.')]);

        return response()->json(['available' => ! $validator->fails(), 'message' => $validator->fails() ? $validator->errors()->first('slug') : __('Available')]);
    }
}
