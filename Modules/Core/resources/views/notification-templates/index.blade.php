<x-layouts::app :title="__('Email templates')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Email templates') => null]">
    <x-card body-class="p-0">
        <p class="small text-body-secondary px-3 pt-3">{{ __('The wording of the emails and in-app notices the system sends. Change one to use your own words; placeholders such as {guest} are filled in when it is sent.') }}</p>
        <div class="table-responsive">
            <table class="table mb-0 align-middle" data-notification-templates>
                <thead><tr><th class="ps-3">{{ __('Template') }}</th><th>{{ __('Sent') }}</th><th>{{ __('Channel') }}</th><th>{{ __('Wording') }}</th><th class="pe-3"></th></tr></thead>
                <tbody>
                    @foreach ($definitions as $definition)
                        @php($isCustom = isset($custom[$definition->key.':'.$definition->channel]))
                        <tr data-template="{{ $definition->key }}">
                            <td class="ps-3">
                                <div class="fw-semibold">{{ __($definition->label ?? $definition->key) }}</div>
                                <div class="small text-body-secondary font-monospace">{{ $definition->key }}</div>
                            </td>
                            <td class="small">{{ $definition->description ? __($definition->description) : '' }}</td>
                            <td><span class="badge text-bg-secondary">{{ $definition->channel === 'mail' ? __('Email') : ($definition->channel === 'database' ? __('In-app') : $definition->channel) }}</span></td>
                            <td>
                                @if ($isCustom)
                                    <span class="badge text-bg-primary">{{ __('Your wording') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('Default') }}</span>
                                @endif
                            </td>
                            <td class="pe-3 text-end text-nowrap">
                                @can('core.notification-template.manage')
                                    <a href="{{ route('core.notification-templates.edit', ['channel' => $definition->channel, 'key' => $definition->key]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
                                    @if ($isCustom)
                                        <form method="POST" action="{{ route('core.notification-templates.destroy', ['channel' => $definition->channel, 'key' => $definition->key]) }}" class="d-inline"
                                            data-confirm="{{ __('Go back to the default wording?') }}" data-confirm-variant="danger">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-counterclockwise"></i> {{ __('Reset') }}</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
