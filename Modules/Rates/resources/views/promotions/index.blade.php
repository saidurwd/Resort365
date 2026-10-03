<x-layouts::app :title="__('Promotions')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Promotions') => null]">
    @can('create', \Modules\Rates\Models\Promotion::class)
        <x-slot:actions>
            <a href="{{ route('rates.promotions.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New promotion') }}</a>
        </x-slot:actions>
    @endcan

    <x-card body-class="p-0">
        @if ($promotions->isEmpty())
            <x-empty-state icon="bi-gift" :title="__('No promotions yet')" :message="__('Add promo codes, or automatic offers such as long-stay discounts.')" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" data-promotions>
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Promotion') }}</th>
                            <th>{{ __('Code') }}</th>
                            <th>{{ __('Discount') }}</th>
                            <th>{{ __('Conditions') }}</th>
                            <th class="text-end">{{ __('Used') }}</th>
                            <th>{{ __('Status') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($promotions as $promotion)
                            <tr>
                                <td class="ps-3"><div class="fw-semibold">{{ $promotion->name }}</div><div class="small text-body-secondary">{{ $promotion->description }}</div></td>
                                <td>@if ($promotion->code)<span class="badge text-bg-light border font-monospace">{{ $promotion->code }}</span>@else<span class="small text-body-secondary">{{ __('Automatic') }}</span>@endif</td>
                                <td class="text-nowrap">
                                    {{ $promotion->discount_type === \Modules\Rates\Enums\DiscountType::Percent ? rtrim(rtrim($promotion->discount_value, '0'), '.').'%' : number_format((float) $promotion->discount_value, 2).' '.$currency }}
                                    <div class="small text-body-secondary">{{ $promotion->discount_type->label() }}</div>
                                </td>
                                <td class="small">
                                    @php
                                        $conditions = array_filter([
                                            $promotion->stay_from || $promotion->stay_to ? __('Stays :from – :to', ['from' => $promotion->stay_from?->format('d M Y') ?? '…', 'to' => $promotion->stay_to?->format('d M Y') ?? '…']) : null,
                                            $promotion->book_from || $promotion->book_to ? __('Booked :from – :to', ['from' => $promotion->book_from?->format('d M Y') ?? '…', 'to' => $promotion->book_to?->format('d M Y') ?? '…']) : null,
                                            $promotion->min_nights ? __('min :n nights', ['n' => $promotion->min_nights]) : null,
                                            $promotion->max_nights ? __('max :n nights', ['n' => $promotion->max_nights]) : null,
                                            $promotion->min_advance_days ? __('booked :n+ days ahead', ['n' => $promotion->min_advance_days]) : null,
                                            $promotion->rate_plan_ids ? implode(', ', array_map(fn ($id) => $planNames[$id] ?? '#'.$id, $promotion->rate_plan_ids)) : null,
                                            $promotion->unit_keys ? implode(', ', array_map(fn ($key) => $unitNames[$key] ?? $key, $promotion->unit_keys)) : null,
                                        ]);
                                    @endphp
                                    {{ $conditions === [] ? __('Any stay') : implode(' · ', $conditions) }}
                                </td>
                                <td class="text-end">{{ $promotion->times_used }}@if ($promotion->usage_limit)/{{ $promotion->usage_limit }}@endif</td>
                                <td>
                                    @if ($promotion->is_active)
                                        <span class="badge text-bg-success">{{ __('Active') }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                    @endif
                                </td>
                                <td class="text-end pe-3 text-nowrap">
                                    @can('update', $promotion)
                                        <a href="{{ route('rates.promotions.edit', $promotion) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                        <x-confirm-delete :action="route('rates.promotions.destroy', $promotion)" icon-only :title="__('Delete :name?', ['name' => $promotion->name])" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>
    <p class="small text-body-secondary">{{ __('Promotions do not combine: a stay gets the single best discount.') }}</p>
</x-layouts::app>
