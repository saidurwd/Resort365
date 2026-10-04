<x-card body-class="p-0">
    @forelse ($outlet->stations as $station)
        <form method="POST" action="{{ route('restaurant.outlets.stations.update', [$outlet, $station]) }}" class="d-flex flex-wrap gap-2 align-items-end px-3 py-2 border-bottom" data-station="{{ $station->name }}">
            @csrf @method('PUT')
            <x-form.input name="name" :id="'station-name-'.$station->id" :label="__('Station')" :value="$station->name" wrapper-class="mb-0" required />
            <x-form.select name="output" :id="'station-output-'.$station->id" :label="__('Tickets to')" :options="$outputs" :value="$station->output->value" :search="false" wrapper-class="mb-0" required />
            <x-form.select name="printer_id" :id="'station-printer-'.$station->id" :label="__('Printer')" :options="$kotPrinters" :value="$station->printer_id" :placeholder="__('None')" wrapper-class="mb-0" />
            @if ($canManage)
                <button type="submit" class="btn btn-outline-primary"><i class="bi bi-save"></i> {{ __('Save') }}</button>
                <button type="submit" form="delete-station-{{ $station->id }}" class="btn btn-outline-danger" aria-label="{{ __('Remove station') }}"><i class="bi bi-trash"></i></button>
            @endif
        </form>
        <form method="POST" action="{{ route('restaurant.outlets.stations.destroy', [$outlet, $station]) }}" id="delete-station-{{ $station->id }}" data-confirm="{{ __('Remove station :name?', ['name' => $station->name]) }}" data-confirm-variant="danger">@csrf @method('DELETE')</form>
    @empty
        <x-empty-state icon="bi-fire" :title="__('No stations yet')" :message="__('E.g. Hot kitchen, Grill, Pastry, Bar: each gets its own kitchen tickets.')" />
    @endforelse
</x-card>
@if ($canManage)
    <x-card :title="__('Add a station')" icon="bi-plus-lg">
        <form method="POST" action="{{ route('restaurant.outlets.stations.store', $outlet) }}" class="d-flex flex-wrap gap-2 align-items-end" data-add-station>
            @csrf
            <x-form.input name="name" id="new-station-name" :label="__('Station')" wrapper-class="mb-0" required />
            <x-form.select name="output" id="new-station-output" :label="__('Tickets to')" :options="$outputs" value="display" :search="false" wrapper-class="mb-0" required />
            <x-form.select name="printer_id" id="new-station-printer" :label="__('Printer')" :options="$kotPrinters" :placeholder="__('None')" wrapper-class="mb-0" />
            <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add station') }}</button>
        </form>
    </x-card>
@endif
