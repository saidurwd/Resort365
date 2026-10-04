@php
    $dayNames = ['mon' => __('Mon'), 'tue' => __('Tue'), 'wed' => __('Wed'), 'thu' => __('Thu'), 'fri' => __('Fri'), 'sat' => __('Sat'), 'sun' => __('Sun')];
@endphp
<x-layouts::app :title="$outlet->name" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Outlets') => route('restaurant.outlets.index'), $outlet->name => null]">
    <x-slot:actions>
        <a href="{{ route('restaurant.outlets.prices', $outlet) }}" class="btn btn-outline-primary" data-price-list-link><i class="bi bi-tags"></i> {{ __('Price list') }}</a>
        @if ($canManage)
            <a href="{{ route('restaurant.outlets.edit', $outlet) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit outlet') }}</a>
        @endif
    </x-slot:actions>

    <ul class="nav nav-tabs mb-3" role="tablist" data-hash-tabs>
        <li class="nav-item" role="presentation"><button class="nav-link active" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-details" data-tab-hash="details"><i class="bi bi-card-text"></i> {{ __('Details') }}</button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-stations" data-tab-hash="stations"><i class="bi bi-fire"></i> {{ __('Stations') }} <span class="badge text-bg-secondary">{{ $outlet->stations->count() }}</span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-terminals" data-tab-hash="terminals"><i class="bi bi-tablet"></i> {{ __('Terminals') }} <span class="badge text-bg-secondary">{{ $outlet->terminals->count() }}</span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" type="button" role="tab" data-bs-toggle="tab" data-bs-target="#tab-floor" data-tab-hash="floor"><i class="bi bi-grid-3x3-gap"></i> {{ __('Floor plan') }} <span class="badge text-bg-secondary">{{ $outlet->areas->sum(fn ($area) => $area->tables->count()) }}</span></button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="tab-details" role="tabpanel">
            <x-card>
                <dl class="row mb-0" data-outlet-details>
                    <dt class="col-sm-3">{{ __('Type') }}</dt><dd class="col-sm-9"><x-status-badge :status="$outlet->type" /> @unless ($outlet->is_active)<span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless</dd>
                    <dt class="col-sm-3">{{ __('Code / bill prefix') }}</dt><dd class="col-sm-9">{{ $outlet->code }} · {{ $outlet->bill_prefix }}</dd>
                    <dt class="col-sm-3">{{ __('Tax') }}</dt><dd class="col-sm-9">{{ $taxCategory ?? __('No tax') }} · {{ $outlet->prices_include_tax ? __('menu prices include tax') : __('tax added to menu prices') }}</dd>
                    <dt class="col-sm-3">{{ __('Opening hours') }}</dt>
                    <dd class="col-sm-9">
                        @foreach ($dayNames as $day => $name)
                            @php($slot = $outlet->opening_hours[$day] ?? null)
                            <span class="me-3">{{ $name }} {{ $slot && $slot['open'] ? $slot['open'].'–'.$slot['close'] : __('closed') }}</span>
                        @endforeach
                    </dd>
                    @if ($outlet->receipt_header)<dt class="col-sm-3">{{ __('Receipt header') }}</dt><dd class="col-sm-9">{{ $outlet->receipt_header }}</dd>@endif
                    @if ($outlet->receipt_footer)<dt class="col-sm-3">{{ __('Receipt footer') }}</dt><dd class="col-sm-9">{{ $outlet->receipt_footer }}</dd>@endif
                </dl>
            </x-card>
        </div>

        <div class="tab-pane fade" id="tab-stations" role="tabpanel">@include('restaurant::outlets.partials.stations')</div>
        <div class="tab-pane fade" id="tab-terminals" role="tabpanel">@include('restaurant::outlets.partials.terminals')</div>
        <div class="tab-pane fade" id="tab-floor" role="tabpanel">@include('restaurant::outlets.partials.floor')</div>
    </div>
</x-layouts::app>
