<x-layouts::app :title="__('API tokens')" :subtitle="__('Access to the REST API')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('API tokens') => null]">
    @unless ($apiAllowed)
        <div class="alert alert-info" data-api-not-in-plan>
            <i class="bi bi-info-circle"></i> {{ __('Your plan does not include API access. Upgrade the plan to create tokens.') }}
        </div>
    @endunless

    @if ($plainToken)
        <div class="alert alert-warning" data-new-token>
            <i class="bi bi-key"></i> {{ __('New token') }}: <span class="font-monospace fw-semibold text-break" data-plain-token>{{ $plainToken }}</span>
            <div class="small">{{ __('Send it as "Authorization: Bearer <token>". It is shown only now; if it is lost, revoke it and make a new one.') }}</div>
        </div>
    @endif

    <x-card :title="__('Tokens')" icon="bi-key" body-class="p-0">
        @if ($tokens->isEmpty())
            <x-empty-state :title="__('No API tokens yet')" :message="__('A token lets another system use the API as you, with only the permissions you give it.')" icon="bi-key" />
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="ps-3">{{ __('Name') }}</th>
                            @if ($showOwner)
                                <th>{{ __('User') }}</th>
                            @endif
                            <th>{{ __('Abilities') }}</th>
                            <th>{{ __('Properties') }}</th>
                            <th>{{ __('Last used') }}</th>
                            <th>{{ __('Expires') }}</th>
                            <th class="pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($tokens as $token)
                            <tr data-token="{{ $token->name }}">
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $token->name }}</div>
                                    <div class="small text-body-secondary">{{ __('Created :date', ['date' => $token->created_at->inPropertyTime()->format('d M Y H:i')]) }}</div>
                                </td>
                                @if ($showOwner)
                                    <td>{{ $token->tokenable?->name }}</td>
                                @endif
                                <td class="small">
                                    @foreach ($token->abilities ?? [] as $ability)
                                        <code class="d-block">{{ $ability }}</code>
                                    @endforeach
                                </td>
                                <td class="small">
                                    @if ($token->property_ids === null)
                                        {{ __('All of the user\'s') }}
                                    @else
                                        {{ collect($token->property_ids)->map(fn ($id) => $properties[$id] ?? '#'.$id)->implode(', ') }}
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    @if ($token->last_used_at)
                                        {{ $token->last_used_at->inPropertyTime()->format('d M Y H:i') }}
                                        <div class="text-body-secondary">{{ $token->last_used_ip }}</div>
                                    @else
                                        <span class="text-body-secondary">{{ __('Never') }}</span>
                                    @endif
                                </td>
                                <td class="small text-nowrap">
                                    @if ($token->expires_at === null)
                                        <span class="text-body-secondary">{{ __('Never') }}</span>
                                    @elseif ($token->isExpired())
                                        <span class="badge text-bg-danger">{{ __('Expired') }}</span>
                                    @else
                                        {{ $token->expires_at->inPropertyTime()->format('d M Y') }}
                                    @endif
                                </td>
                                <td class="text-end pe-3">
                                    @can('delete', $token)
                                        <x-confirm-delete :action="route('iam.api-tokens.destroy', $token)" icon-only :title="__('Revoke token :name?', ['name' => $token->name])" />
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @if ($apiAllowed)
        @can('create', \Modules\IAM\Models\ApiToken::class)
            <form method="POST" action="{{ route('iam.api-tokens.store') }}" data-new-token-form>
                @csrf
                <x-card :title="__('New token')" icon="bi-plus-lg">
                    <div class="row">
                        <div class="col-md-4"><x-form.input name="name" :label="__('Name')" :help="__('What uses it, e.g. Booking website.')" required /></div>
                        <div class="col-md-5">
                            <x-form.select name="property_ids[]" :label="__('Properties')" :options="$properties" :value="old('property_ids', [])" multiple
                                :placeholder="__('All of mine')" :help="__('Leave empty for every property you may work in.')" />
                        </div>
                        <div class="col-md-3"><x-form.date name="expires_at" :label="__('Expires')" :min="now()->addDay()->toDateString()" :help="__('Leave empty to keep it until revoked.')" /></div>
                    </div>
                    <h6 class="mt-2">{{ __('What it may do') }}</h6>
                    <p class="small text-body-secondary">{{ __('Only your own permissions are listed. Give it no more than the other system needs.') }}</p>
                    @error('abilities')
                        <div class="alert alert-danger py-2">{{ $message }}</div>
                    @enderror
                    @include('iam::roles.partials.permissions', ['editable' => true, 'granted' => [], 'groups' => $groups, 'field' => 'abilities'])
                    <x-slot:footer>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-key"></i> {{ __('Create token') }}</button>
                        </div>
                    </x-slot:footer>
                </x-card>
            </form>
        @endcan
    @endif
</x-layouts::app>
