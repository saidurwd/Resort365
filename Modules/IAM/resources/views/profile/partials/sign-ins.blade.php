<x-card :title="__('Recent sign-in activity')" icon="bi-clock-history" body-class="p-0">
    <div class="px-3 pt-3 small text-body-secondary">
        @if ($user->last_login_at)
            {{ __('Last signed in :when from :ip.', ['when' => $user->last_login_at->format('d M Y H:i'), 'ip' => $user->last_login_ip]) }}
        @endif
    </div>
    @if ($signIns->isEmpty())
        <x-empty-state icon="bi-clock-history" :title="__('No sign-in activity yet')" class="py-4" />
    @else
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">{{ __('When') }}</th>
                        <th>{{ __('Event') }}</th>
                        <th>{{ __('IP address') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($signIns as $signIn)
                        <tr>
                            <td class="ps-3 text-nowrap">{{ $signIn->created_at->format('d M Y H:i') }}</td>
                            <td><x-status-badge :status="$signIn->event" /></td>
                            <td class="small">{{ $signIn->ip_address }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-card>
