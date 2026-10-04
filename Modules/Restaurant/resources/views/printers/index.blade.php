<x-layouts::app :title="__('Printers')" :subtitle="$propertyName" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Restaurant') => null, __('Printers') => null]">
    <div class="row">
        <div @class(['col-xl-8' => $canManage, 'col-12' => ! $canManage])>
            <x-card body-class="p-0">
                @if ($printers->isEmpty())
                    <x-empty-state icon="bi-printer" :title="__('No printers yet')" />
                @else
                    <table class="table align-middle mb-0" data-printers>
                        <thead><tr><th class="ps-3">{{ __('Printer') }}</th><th>{{ __('Type') }}</th><th>{{ __('Connection') }}</th><th>{{ __('Paper') }}</th><th>{{ __('Used by') }}</th><th class="pe-3"></th></tr></thead>
                        <tbody>
                            @foreach ($printers as $printer)
                                <tr data-printer="{{ $printer->id }}" @class(['text-body-secondary' => ! $printer->is_active])>
                                    <td class="ps-3 fw-semibold">{{ $printer->name }}</td>
                                    <td><x-status-badge :status="$printer->type" /></td>
                                    <td>{{ $printer->connection->label() }}@if ($printer->address)<div class="small text-body-secondary">{{ $printer->address }}</div>@endif</td>
                                    <td>{{ $printer->paper_width_mm }} mm</td>
                                    <td class="small">{{ ($usedBy[$printer->id] ?? collect())->map(fn ($station) => $station->outlet->name.' · '.$station->name)->implode(', ') }}</td>
                                    <td class="pe-3 text-end">
                                        @if ($canManage)
                                            <form method="POST" action="{{ route('restaurant.printers.destroy', $printer) }}" data-confirm="{{ __('Remove printer :name?', ['name' => $printer->name]) }}" data-confirm-variant="danger">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="{{ __('Remove') }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>
            <p class="small text-body-secondary">{{ __('Printing goes through the browser\'s print dialog for now; silent printing to network printers comes later.') }}</p>
        </div>
        @if ($canManage)
            <div class="col-xl-4">
                <x-card :title="__('Add a printer')" icon="bi-printer">
                    <form method="POST" action="{{ route('restaurant.printers.store') }}" data-printer-form>
                        @csrf
                        <x-form.input name="name" :label="__('Name')" required />
                        <x-form.select name="type" :label="__('Type')" :options="$types" :search="false" required />
                        <x-form.select name="connection" :label="__('Connection')" :options="$connections" :value="old('connection', 'browser')" :search="false" required />
                        <x-form.input name="address" :label="__('Address')" :help="__('IP address and port for a network printer.')" />
                        <x-form.select name="paper_width_mm" :label="__('Paper width')" :options="[80 => '80 mm', 58 => '58 mm']" :value="old('paper_width_mm', 80)" :search="false" required />
                        <input type="hidden" name="is_active" value="1">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-plus-lg"></i> {{ __('Add printer') }}</button>
                    </form>
                </x-card>
            </div>
        @endif
    </div>
</x-layouts::app>
