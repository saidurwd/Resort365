<x-layouts::kds :title="__('Kitchen display')">
    <div class="pos-card mx-auto" data-kds-stations>
        <h1 class="h3"><i class="bi bi-display"></i> {{ __('Choose a station') }}</h1>
        @forelse ($outlets as $outlet)
            @php($stations = $outlet->stations->filter(fn ($station) => $station->hasDisplay()))
            @continue($stations->isEmpty())
            <h2 class="h6 mt-3">{{ $outlet->name }}</h2>
            <div class="d-flex flex-wrap gap-2">
                @foreach ($stations as $station)
                    <a href="{{ route('kds.board', ['station' => $station->id]) }}" class="btn btn-outline-primary btn-lg pos-btn" data-station="{{ $station->name }}">{{ $station->name }}</a>
                @endforeach
            </div>
        @empty
            <x-empty-state icon="bi-fire" :title="__('No outlets for you')" :message="__('You do not work in any outlet with a kitchen display.')" />
        @endforelse
        <a href="{{ route('dashboard') }}" class="btn btn-link mt-3 px-0"><i class="bi bi-arrow-left"></i> {{ __('Back to Resort365') }}</a>
    </div>
</x-layouts::kds>
