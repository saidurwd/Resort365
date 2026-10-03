{{-- TODO: stay history and lifetime value — not yet scheduled (ARCHITECTURE §5.8); reservations exist since Step 1.6. --}}
<x-layouts::app :title="$guest->full_name" :breadcrumbs="[__('Guests') => route('guest.guests.index'), $guest->full_name => null]">
    <x-slot:actions>
        @can('update', $guest)
            <a href="{{ route('guest.guests.edit', $guest) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>
        @endcan
    </x-slot:actions>

    @if ($guest->is_blacklisted)
        <div class="alert alert-danger d-flex gap-2" role="alert" data-blacklist-banner>
            <i class="bi bi-slash-circle fs-5"></i>
            <div>
                <div class="fw-semibold">{{ __('Blacklisted') }}@if ($guest->blacklisted_at) · {{ $guest->blacklisted_at->format('d M Y') }}@endif</div>
                <div>{{ $guest->blacklist_reason }}</div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Guest')" icon="bi-person">
                <dl class="row mb-0">
                    <dt class="col-sm-4">{{ __('Phone') }}</dt>
                    <dd class="col-sm-8">{{ $guest->phone ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Email') }}</dt>
                    <dd class="col-sm-8">{{ $guest->email ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Nationality') }}</dt>
                    <dd class="col-sm-8">{{ $guest->nationality_code ? ($countries[$guest->nationality_code] ?? $guest->nationality_code) : '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Date of birth') }}</dt>
                    <dd class="col-sm-8">{{ $guest->date_of_birth?->format('d M Y') ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('ID document') }}</dt>
                    <dd class="col-sm-8" data-id-number>
                        @if ($guest->id_type)
                            {{ $guest->id_type->label() }}:
                            @can('viewId', $guest)
                                <span class="font-monospace">{{ $guest->id_number }}</span>
                            @else
                                <span class="font-monospace">{{ $guest->maskedIdNumber() }}</span>
                            @endcan
                            @if ($guest->id_expiry)<span class="text-body-secondary"> · {{ __('expires :date', ['date' => $guest->id_expiry->format('d M Y')]) }}</span>@endif
                        @else
                            —
                        @endif
                    </dd>
                    <dt class="col-sm-4">{{ __('Address') }}</dt>
                    <dd class="col-sm-8">{{ collect($guest->address ?? [])->only(['line1', 'line2', 'city', 'postal_code', 'country_code'])->filter()->implode(', ') ?: '—' }}</dd>
                    <dt class="col-sm-4">{{ __('Company') }}</dt>
                    <dd class="col-sm-8">{{ $guest->company?->name ?? '—' }}</dd>
                    <dt class="col-sm-4">{{ __('VIP level') }}</dt>
                    <dd class="col-sm-8"><x-status-badge :status="$guest->vip_level" /></dd>
                    <dt class="col-sm-4">{{ __('Preferences') }}</dt>
                    <dd class="col-sm-8">
                        @forelse ($guest->preferences ?? [] as $preference)
                            <span class="badge text-bg-light border">{{ $preference }}</span>
                        @empty
                            —
                        @endforelse
                    </dd>
                    <dt class="col-sm-4">{{ __('Marketing') }}</dt>
                    <dd class="col-sm-8">{{ $guest->marketing_consent ? __('Agrees to receive offers') : __('No offers') }}</dd>
                    @if ($guest->notes)
                        <dt class="col-sm-4">{{ __('Notes') }}</dt>
                        <dd class="col-sm-8 mb-0">{!! nl2br(e($guest->notes)) !!}</dd>
                    @endif
                </dl>
            </x-card>

            <x-card :title="__('Possible duplicates')" icon="bi-people" body-class="p-0" data-duplicates>
                @if ($duplicates->isEmpty())
                    <p class="text-body-secondary px-3 py-3 mb-0">{{ __('No other guest has the same phone, email or ID.') }}</p>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach ($duplicates as $duplicate)
                            <li class="list-group-item d-flex flex-wrap align-items-center gap-2" data-duplicate>
                                <div class="flex-grow-1">
                                    <a href="{{ route('guest.guests.show', $duplicate) }}" class="fw-semibold">{{ $duplicate->full_name }}</a>
                                    <span class="text-body-secondary small">{{ collect([$duplicate->phone, $duplicate->email])->filter()->implode(' · ') }}</span>
                                    @foreach ($matchedOn($duplicate) as $match)
                                        <span class="badge text-bg-warning">{{ match ($match) { 'phone' => __('Same phone'), 'email' => __('Same email'), default => __('Same ID') } }}</span>
                                    @endforeach
                                </div>
                                @can('merge', $guest)
                                    <form method="POST" action="{{ route('guest.guests.merge', $guest) }}"
                                          data-confirm="{{ __('Merge :duplicate into this profile?', ['duplicate' => $duplicate->full_name]) }}"
                                          data-confirm-text="{{ __('Its details fill the gaps here, its ID documents move here, and it is removed. This cannot be undone.') }}"
                                          data-confirm-button="{{ __('Merge') }}" data-confirm-cancel="{{ __('Cancel') }}">
                                        @csrf
                                        <input type="hidden" name="duplicate_id" value="{{ $duplicate->id }}">
                                        <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-intersect"></i> {{ __('Merge into this profile') }}</button>
                                    </form>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @can('merge', $guest)
                    <x-slot:footer>
                        <form method="POST" action="{{ route('guest.guests.merge', $guest) }}" class="row g-2 align-items-end"
                              data-confirm="{{ __('Merge the chosen guest into this profile?') }}" data-confirm-text="{{ __('This cannot be undone.') }}"
                              data-confirm-button="{{ __('Merge') }}" data-confirm-cancel="{{ __('Cancel') }}">
                            @csrf
                            <div class="col">
                                <x-form.select name="duplicate_id" :label="__('Merge another guest into this profile')" :options="[]" :placeholder="__('Type a name or phone…')"
                                    :tom-options="['remote' => route('guest.guests.search')]" wrapper-class="mb-0" />
                            </div>
                            <div class="col-auto"><button type="submit" class="btn btn-outline-primary">{{ __('Merge') }}</button></div>
                        </form>
                    </x-slot:footer>
                @endcan
            </x-card>
        </div>

        <div class="col-xl-4">
            @can('viewAttachments', $guest)
                <x-attachments :subject="$guest" :title="__('ID documents')" />
            @endcan

            @can('blacklist', $guest)
                <x-card :title="__('Blacklist')" icon="bi-slash-circle" data-blacklist>
                    @if ($guest->is_blacklisted)
                        <form method="POST" action="{{ route('guest.guests.blacklist.clear', $guest) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-success w-100"><i class="bi bi-check-circle"></i> {{ __('Remove from blacklist') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('guest.guests.blacklist', $guest) }}">
                            @csrf
                            <x-form.field name="reason" :label="__('Reason')" required>
                                <textarea name="reason" id="field-reason" rows="2" required @class(['form-control', 'is-invalid' => $errors->has('reason')])>{{ old('reason') }}</textarea>
                            </x-form.field>
                            <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-slash-circle"></i> {{ __('Blacklist guest') }}</button>
                        </form>
                    @endif
                </x-card>
            @endcan

            <x-audit-trail :entries="$history" />
        </div>
    </div>
</x-layouts::app>
