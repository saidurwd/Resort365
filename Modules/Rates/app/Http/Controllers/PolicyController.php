<?php

namespace Modules\Rates\Http\Controllers;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Modules\Property\Contracts\PropertyDirectory;
use Modules\Rates\DTOs\CancellationQuote;
use Modules\Rates\DTOs\DepositQuote;
use Modules\Rates\Http\Controllers\Concerns\UsesCurrentProperty;
use Modules\Rates\Http\Requests\TryPoliciesRequest;
use Modules\Rates\Models\CancellationPolicy;
use Modules\Rates\Models\DepositPolicy;
use Modules\Rates\Models\RatePlan;
use Modules\Rates\Services\CancellationFeeCalculator;
use Modules\Rates\Services\DepositCalculator;

/**
 * Rates → Policies: the property's deposit and cancellation policies, with a "try it" box that
 * shows the deposit of a booking and the fee of cancelling it.
 */
class PolicyController extends Controller
{
    use UsesCurrentProperty;

    public function index(TryPoliciesRequest $request, DepositCalculator $deposits, CancellationFeeCalculator $fees, PropertyDirectory $properties): View
    {
        Gate::authorize('viewAny', DepositPolicy::class);
        $propertyId = $this->currentPropertyId();

        $depositPolicies = DepositPolicy::query()->where('property_id', $propertyId)->orderByDesc('is_default')->orderBy('name')->get();
        $cancellationPolicies = CancellationPolicy::query()->where('property_id', $propertyId)->with('rules')->orderByDesc('is_default')->orderBy('name')->get();
        $usage = RatePlan::query()->where('property_id', $propertyId)->get(['name', 'deposit_policy_id', 'cancellation_policy_id']);

        [$deposit, $cancellation] = [null, null];
        $depositPolicy = $depositPolicies->firstWhere('id', $request->integer('deposit_policy')) ?? $depositPolicies->firstWhere('is_default', true);
        $cancellationPolicy = $cancellationPolicies->firstWhere('id', $request->integer('cancellation_policy')) ?? $cancellationPolicies->firstWhere('is_default', true);

        if ($request->filled(['total', 'arrival']) && $depositPolicy instanceof DepositPolicy) {
            [$deposit, $cancellation] = $this->tryIt($request, $depositPolicy, $cancellationPolicy, $deposits, $fees, $properties, $propertyId);
        }

        return view('rates::policies.index', [
            'propertyName' => $this->currentPropertyName(),
            'depositPolicies' => $depositPolicies,
            'cancellationPolicies' => $cancellationPolicies,
            'plansUsing' => fn (string $column, int $id): array => $usage->where($column, $id)->pluck('name')->all(),
            'deposit' => $deposit,
            'cancellation' => $cancellation,
            'tryDeposit' => $depositPolicy,
            'tryCancellation' => $cancellationPolicy,
            'currency' => $this->currency($propertyId),
        ]);
    }

    /**
     * @return array{DepositQuote, CancellationQuote|null}
     */
    private function tryIt(TryPoliciesRequest $request, DepositPolicy $depositPolicy, ?CancellationPolicy $cancellationPolicy, DepositCalculator $deposits,
        CancellationFeeCalculator $fees, PropertyDirectory $properties, int $propertyId): array
    {
        $property = $properties->find($propertyId);
        $timezone = $property->timezone ?? 'UTC';
        $total = (string) BigDecimal::of((string) $request->input('total'))->toScale(2, RoundingMode::HalfUp);
        $nights = max(1, $request->integer('nights', 1));
        $nightly = array_fill(0, $nights, (string) BigDecimal::of($total)->dividedBy($nights, 2, RoundingMode::Down));
        $arrival = CarbonImmutable::parse($request->input('arrival').' '.($property->checkInTime ?? '14:00'), $timezone);

        $deposit = $deposits->quote($depositPolicy->terms(), $total, $nightly[0], $request->filled('percent') ? (string) $request->input('percent') : null,
            CarbonImmutable::now($timezone), $arrival);

        $cancellation = $cancellationPolicy instanceof CancellationPolicy && ($request->filled('cancel_on') || $request->boolean('no_show'))
            ? $fees->quote($cancellationPolicy->terms(), $total, $nightly, $deposit->amount, $deposit->amount,
                $request->boolean('no_show') ? null : CancellationFeeCalculator::daysBefore($arrival, CarbonImmutable::parse((string) $request->input('cancel_on'), $timezone)))
            : null;

        return [$deposit, $cancellation];
    }
}
