<x-layouts::platform :title="__('Plans')" :breadcrumbs="[__('Platform') => route('platform.console'), __('Plans') => null]">
    <x-slot:actions><a href="{{ route('platform.plans.create') }}" class="btn btn-sm btn-primary" data-new-plan><i class="bi bi-plus-lg"></i> {{ __('New plan') }}</a></x-slot:actions>
    <div class="row g-3" data-plans>
        @forelse ($plans as $plan)
            <div class="col-md-6 col-xl-4">
                <x-card :title="$plan->name" icon="bi-box-seam" data-plan="{{ $plan->code }}">
                    <div class="d-flex justify-content-between"><span class="fs-4 font-monospace">{{ $plan->currency_code }} {{ number_format((float) $plan->monthly_price, 2) }}</span><span class="text-body-secondary small align-self-end">{{ __('a month') }}</span></div>
                    <p class="small text-body-secondary">{{ $plan->description }}</p>
                    <ul class="list-unstyled small mb-2">
                        <li>{{ __('Properties') }}: <strong>{{ $plan->max_properties ?? __('no limit') }}</strong></li>
                        <li>{{ __('Rooms') }}: <strong>{{ $plan->max_rooms ?? __('no limit') }}</strong></li>
                        <li>{{ __('Users') }}: <strong>{{ $plan->max_users ?? __('no limit') }}</strong></li>
                        <li>{{ __('Storage') }}: <strong>{{ $plan->storage_gb !== null ? $plan->storage_gb.' GB' : __('no limit') }}</strong></li>
                        <li>{{ __('API access') }}: <strong>{{ $plan->api_access ? __('Yes') : __('No') }}</strong></li>
                    </ul>
                    <p class="small mb-2">@foreach ($plan->modules as $module)<span class="badge text-bg-light border">{{ $modules[$module] ?? $module }}</span> @endforeach</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small text-body-secondary">{{ trans_choice(':count tenant|:count tenants', $tenants[$plan->id] ?? 0) }} @unless ($plan->is_active) · {{ __('inactive') }} @endunless</span>
                        <span>
                            <a href="{{ route('platform.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary" data-edit-plan>{{ __('Edit') }}</a>
                            <form method="POST" action="{{ route('platform.plans.destroy', $plan) }}" class="d-inline" data-confirm="{{ __('Delete the :name plan?', ['name' => $plan->name]) }}" data-confirm-variant="danger">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" data-delete-plan><i class="bi bi-trash"></i></button></form>
                        </span>
                    </div>
                </x-card>
            </div>
        @empty
            <div class="col-12"><x-empty-state :title="__('No plans yet')" /></div>
        @endforelse
    </div>
</x-layouts::platform>
