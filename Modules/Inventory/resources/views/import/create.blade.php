<x-layouts::app :title="__('Import items')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Inventory') => null, __('Import items') => null]">
    <div class="row">
        <div class="col-xl-6">
            <x-card :title="__('CSV file')" icon="bi-upload">
                <form method="POST" action="{{ route('inventory.import.store') }}" enctype="multipart/form-data" data-import-form>
                    @csrf
                    <div class="mb-3">
                        <label class="form-label" for="items-file">{{ __('File') }}</label>
                        <input type="file" class="form-control @error('file') is-invalid @enderror" id="items-file" name="file" accept=".csv,text/csv,text/plain" required>
                        @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> {{ __('Import') }}</button>
                </form>
            </x-card>
        </div>
        <div class="col-xl-6">
            <x-card :title="__('What the file looks like')" icon="bi-info-circle">
                <p class="small">{{ __('A header row, then one item per line. Needed: sku, name, category, base_unit. Optional: barcode, purchase_unit, issue_unit, reorder_level, reorder_qty, standard_cost, stockable, batches, expiry, notes.') }}</p>
                <pre class="small bg-body-tertiary p-2 rounded mb-2">sku,name,category,base_unit,reorder_level,standard_cost
RICE-5,Basmati rice,Food > Dry goods,kg,50,120.00
TWL-B,Bath towel,Housekeeping > Linen,pcs,100,450.00</pre>
                <p class="small text-body-secondary mb-0">{{ __('An item whose SKU exists is updated; new categories are created; units must exist (see Units). The whole file is refused if any line is wrong.') }}</p>
            </x-card>
        </div>
    </div>
</x-layouts::app>
