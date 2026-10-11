<x-layouts::print :title="__('Count sheet').' '.$count->count_no">
    <h1 class="h4">{{ __('Count sheet') }} {{ $count->count_no }}</h1>
    <p>{{ $count->store->name }} · {{ $count->started_at->inPropertyTime()->format('d M Y H:i') }}</p>
    <table class="table table-sm table-bordered">
        <thead><tr><th>{{ __('Item') }}</th><th>{{ __('SKU') }}</th><th>{{ __('Unit') }}</th><th class="text-end">{{ __('Counted') }}</th></tr></thead>
        <tbody>
            @foreach ($lines as $line)
                <tr><td>{{ $line->item->name }}</td><td>{{ $line->item->sku }}</td><td>{{ $line->item->baseUnit->code }}</td><td style="width:25%"></td></tr>
            @endforeach
        </tbody>
    </table>
</x-layouts::print>
