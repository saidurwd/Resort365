{{-- field => [old, new] --}}
@if (empty($changes))
    <span class="text-body-tertiary">—</span>
@else
    <dl class="small mb-0">
        @foreach ($changes as $field => [$old, $new])
            <div>
                <dt class="d-inline fw-normal text-body-secondary">{{ \Illuminate\Support\Str::headline($field) }}:</dt>
                <dd class="d-inline"><del>{{ is_bool($old) ? ($old ? 'yes' : 'no') : ($old ?? '—') }}</del> → <ins class="text-decoration-none">{{ is_bool($new) ? ($new ? 'yes' : 'no') : ($new ?? '—') }}</ins></dd>
            </div>
        @endforeach
    </dl>
@endif
