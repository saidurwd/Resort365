<x-layouts::platform :title="$tenant->name" :subtitle="$tenant->domain()" :breadcrumbs="[__('Platform') => route('platform.console'), __('Tenants') => route('platform.tenants.index'), $tenant->name => null]">
    <div class="row">
        <div class="col-lg-7">
            <x-card :title="__('Usage')" icon="bi-bar-chart">
                <div data-usage-list>
                    @foreach ($usage as $name => $row)
                        <div class="mb-3" data-meter="{{ $name }}">
                            <div class="d-flex justify-content-between small"><span>{{ $row['label'] }}</span>
                                <span class="font-monospace">{{ number_format((float) $row['used'], $row['unit'] === 'GB' ? 2 : 0) }} @if ($row['limit'] !== null) / {{ number_format((float) $row['limit'], 0) }} @else <span class="text-body-secondary">{{ __('no limit') }}</span> @endif {{ $row['unit'] === 'GB' ? 'GB' : '' }}</span></div>
                            @if ($row['percent'] !== null)
                                <progress class="w-100" max="100" value="{{ $row['percent'] }}" data-percent="{{ $row['percent'] }}">{{ $row['percent'] }}%</progress>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-card>

            <x-card :title="__('Sign in as one of its people')" icon="bi-person-badge">
                @if ($people === [])
                    <p class="text-body-secondary mb-0" data-no-people>{{ $tenant->status->canAccess() ? __('No active users.') : __('The tenant is not active, so nobody can sign in to it.') }}</p>
                @else
                    <form method="POST" action="{{ route('platform.tenants.impersonate', $tenant) }}" class="row g-2 align-items-end" data-impersonate-form>
                        @csrf
                        <div class="col-md-4"><x-form.select name="user_id" :label="__('Person')" :options="collect($people)->mapWithKeys(fn ($u) => [$u->id => $u->name.' · '.$u->email])->all()" required :search="false" wrapper-class="mb-0" /></div>
                        <div class="col-md-5"><x-form.input name="reason" :label="__('Why')" required wrapper-class="mb-0" /></div>
                        <div class="col-md-3"><button type="submit" class="btn btn-outline-danger w-100" data-impersonate>{{ __('Sign in as') }}</button></div>
                    </form>
                    <p class="small text-body-secondary mt-2 mb-0">{{ __('You go to the tenant\'s own address with a link that works once, for a minute. Everything you do there is recorded against your name.') }}</p>
                @endif
            </x-card>

            <x-card :title="__('Recent sign-ins as a user')" icon="bi-clock-history" body-class="p-0">
                <table class="table table-sm mb-0" data-sessions>
                    <tbody>
                        @forelse ($sessions as $session)
                            <tr><td class="ps-3">{{ $session->admin?->name }} → {{ $session->user_name }}<div class="small text-body-secondary">{{ $session->reason }}</div></td><td class="small">{{ $session->started_at?->format('d M H:i') ?? __('Not used') }}@if ($session->ended_at) – {{ $session->ended_at->format('H:i') }} @endif</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('Never.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>

        <div class="col-lg-5">
            <x-card :title="__('Status')" icon="bi-activity">
                <p class="mb-2"><x-status-badge :status="$tenant->status" /> @if ($tenant->trial_ends_at) <span class="small text-body-secondary">{{ __('Trial ends :date', ['date' => $tenant->trial_ends_at->format('d M Y')]) }}</span> @endif</p>
                @if ($tenant->status === \App\Enums\TenantStatus::Suspended)
                    <form method="POST" action="{{ route('platform.tenants.reactivate', $tenant) }}">@csrf<button type="submit" class="btn btn-success" data-reactivate>{{ __('Reactivate') }}</button></form>
                @elseif ($tenant->status->canAccess())
                    <form method="POST" action="{{ route('platform.tenants.suspend', $tenant) }}" class="d-flex gap-2" data-suspend-form data-confirm="{{ __('Suspend :tenant?', ['tenant' => $tenant->name]) }}" data-confirm-text="{{ __('Its people see the suspended page until you reactivate it.') }}" data-confirm-variant="danger">
                        @csrf
                        <input type="text" name="reason" class="form-control" placeholder="{{ __('Reason') }}" required>
                        <button type="submit" class="btn btn-outline-danger" data-suspend>{{ __('Suspend') }}</button>
                    </form>
                @endif
            </x-card>

            <x-card :title="__('Plan')" icon="bi-box-seam">
                <form method="POST" action="{{ route('platform.tenants.plan', $tenant) }}" data-plan-form>
                    @csrf @method('PUT')
                    <x-form.select name="plan_id" :label="__('Moves the tenant to')" :options="$plans->mapWithKeys(fn ($p) => [$p->id => $p->name.($p->is_active ? '' : ' ('.__('inactive').')')])->all()" :value="$plan?->id" :placeholder="__('No plan (every module, no limits)')" :search="false" />
                    <button type="submit" class="btn btn-primary" data-change-plan>{{ __('Apply the plan') }}</button>
                </form>
                @if ($plan)
                    <p class="small text-body-secondary mt-2 mb-0" data-plan-modules>{{ __('Modules: :list', ['list' => implode(', ', array_map(fn ($m) => \Modules\Platform\Services\ModuleCatalog::labels()[$m] ?? $m, $plan->modules))]) }}</p>
                @endif
            </x-card>

            <x-card :title="__('Audit')" icon="bi-journal-text" body-class="p-0">
                <table class="table table-sm mb-0" data-tenant-audit>
                    <tbody>
                        @forelse ($audit as $entry)
                            <tr><td class="ps-3">{{ $entry->summary }}<div class="small text-body-secondary">{{ $entry->admin_name }}</div></td><td class="small pe-3">{{ $entry->created_at->format('d M H:i') }}</td></tr>
                        @empty
                            <tr><td class="ps-3 text-body-secondary">{{ __('Nothing yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </x-card>
        </div>
    </div>
</x-layouts::platform>
