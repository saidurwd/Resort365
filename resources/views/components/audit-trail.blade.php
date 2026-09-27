@props([
    'entries' => [],
    'title' => null,
])

{{--
    Change history, newest first. Presentational until the audit log service (TODO(step-0.7)).
    entries: list of ['description', 'causer' (?string), 'at' (Carbon), 'changes' => [field => [old, new]]].
--}}
<x-card :title="$title ?? __('History')" icon="bi-clock-history" {{ $attributes }}>
    @if (empty($entries))
        <x-empty-state icon="bi-clock-history" :title="__('No changes recorded')" class="py-3" />
    @else
        <ul class="audit-trail">
            @foreach ($entries as $entry)
                <li>
                    <div class="fw-semibold">{{ $entry['description'] }}</div>
                    <div class="small text-body-secondary">
                        {{ $entry['causer'] ?? __('System') }} · <time datetime="{{ $entry['at']->toIso8601String() }}">{{ $entry['at']->format('d M Y H:i') }}</time>
                    </div>
                    @if (! empty($entry['changes']))
                        <dl class="small mb-0 mt-1">
                            @foreach ($entry['changes'] as $field => [$old, $new])
                                <dt class="d-inline fw-normal text-body-secondary">{{ \Illuminate\Support\Str::headline($field) }}:</dt>
                                <dd class="d-inline me-2"><del>{{ $old ?? '—' }}</del> → <ins class="text-decoration-none">{{ $new ?? '—' }}</ins></dd>
                            @endforeach
                        </dl>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</x-card>
