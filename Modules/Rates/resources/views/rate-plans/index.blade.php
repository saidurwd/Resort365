@php($categoryNames = collect($taxCategories)->mapWithKeys(fn ($c) => [$c->id => $c->name]))

<x-layouts::app :title="__('Rate plans')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Rate plans') => null]">
    @can('create', \Modules\Rates\Models\RatePlan::class)
        <x-slot:actions>
            <a href="{{ route('rates.rate-plans.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New rate plan') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($plans->isEmpty())
            <x-empty-state icon="bi-tags" :title="__('No rate plans yet')" :message="__('Create e.g. Room Only and Bed & Breakfast, then enter their rates.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-rate-plans>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Rate plan') }}</th>
                            <th>{{ __('Meals') }}</th>
                            <th class="text-end">{{ __('Meal value / adult') }}</th>
                            <th>{{ __('Taxes') }}</th>
                            <th>{{ __('Policies') }}</th>
                            <th>{{ __('Valid') }}</th>
                            <th class="text-end">{{ __('Rates') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($plans as $plan)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $plan->name }}</div>
                                    <div class="small text-body-secondary">{{ $plan->code }} · {{ $plan->is_refundable ? __('Refundable') : __('Non-refundable') }}</div>
                                </td>
                                <td><x-status-badge :status="$plan->meal_plan" /></td>
                                <td class="text-end font-monospace">{{ $plan->meal_plan->includesMeals() ? number_format((float) $plan->meal_adult_amount, 2) : '—' }}</td>
                                <td>
                                    {{ $plan->tax_category_id ? ($categoryNames[$plan->tax_category_id] ?? '—') : __('None') }}
                                    <div class="small text-body-secondary">{{ $plan->prices_include_tax ? __('Prices include tax') : __('Tax added on top') }}</div>
                                </td>
                                <td class="small">
                                    <div>{{ $plan->depositPolicy?->name ?? ($defaultDeposit ? $defaultDeposit.' ('.__('default').')' : '—') }}</div>
                                    <div class="text-body-secondary">{{ $plan->cancellationPolicy?->name ?? ($defaultCancellation ? $defaultCancellation.' ('.__('default').')' : '—') }}</div>
                                </td>
                                <td class="small text-nowrap">
                                    @if ($plan->valid_from || $plan->valid_to)
                                        {{ $plan->valid_from?->format('d M Y') ?? '…' }} – {{ $plan->valid_to?->format('d M Y') ?? '…' }}
                                    @else
                                        {{ __('Always') }}
                                    @endif
                                </td>
                                <td class="text-end">{{ $plan->rates_count }}</td>
                                <td>
                                    @if ($plan->is_active)
                                        <span class="badge text-bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('viewRates', $plan)
                                        <a href="{{ route('rates.rate-plans.rates.edit', $plan) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-cash-stack"></i> {{ __('Rates') }}</a>
                                    @endcan
                                    @can('update', $plan)
                                        <a href="{{ route('rates.rate-plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                    @endcan
                                    @can('delete', $plan)
                                        <x-confirm-delete :action="route('rates.rate-plans.destroy', $plan)" icon-only :title="__('Delete rate plan :name?', ['name' => $plan->name])" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="small text-body-secondary">{{ __('Amounts in :currency.', ['currency' => $currency]) }}</p>
</x-layouts::app>
