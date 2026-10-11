<x-layouts::app :title="__('Stock counts')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Stock counts') => null]">
    @if ($canManage)
        <x-card :title="__('Start a count')" icon="bi-clipboard-data">
            <form method="POST" action="{{ route('inventory.counts.store') }}" class="row g-2 align-items-end" data-start-count>
                @csrf
                <div class="col-md-3"><x-form.select name="store_id" :label="__('Store')" :options="$stores" :value="old('store_id')" :placeholder="__('Choose a store')" required /></div>
                <div class="col-md-3"><x-form.select name="item_category_id" :label="__('Category (optional)')" :options="$categories" :value="old('item_category_id')" :placeholder="__('Whole store')" /></div>
                <div class="col-md-3"><x-form.input name="notes" :label="__('Notes')" :value="old('notes')" /></div>
                <div class="col-md-2"><div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="blind" value="1" id="blind" @checked(old('blind'))><label class="form-check-label" for="blind">{{ __('Blind count') }}</label></div></div>
                <div class="col-md-1"><button type="submit" class="btn btn-primary mb-3">{{ __('Start') }}</button></div>
            </form>
        </x-card>
    @endif
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0" data-counts>
                <thead><tr><th class="ps-3">{{ __('Count') }}</th><th>{{ __('Store') }}</th><th class="text-end">{{ __('Items') }}</th><th>{{ __('Started') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @forelse ($counts as $count)
                        <tr data-count="{{ $count->count_no }}">
                            <td class="ps-3">{{ $count->count_no }}@if ($count->blind) <span class="badge text-bg-secondary">{{ __('Blind') }}</span>@endif</td><td>{{ $count->store->name }}</td><td class="text-end">{{ $count->lines_count }}</td>
                            <td>{{ $count->started_at->inPropertyTime()->format('d M Y H:i') }}</td><td><x-status-badge :status="$count->status" /></td>
                            <td class="text-end pe-3"><a href="{{ route('inventory.counts.show', $count) }}" class="btn btn-sm btn-outline-secondary">{{ __('Open') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty-state :title="__('No stock counts yet')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
