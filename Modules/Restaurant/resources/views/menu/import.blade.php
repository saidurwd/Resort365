<x-layouts::app :title="__('Import menu')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Menu') => route('restaurant.menu.items.index'), __('Import') => null]">
    <div class="row">
        <div class="col-xl-7">
            <x-card :title="__('Upload a CSV')" icon="bi-upload">
                <p>{{ __('Items are matched by code: existing ones are updated, new ones added. Categories are created from their path (Food > Mains). Every line is checked first; if any line has a problem nothing is imported.') }}</p>
                <form method="POST" action="{{ route('restaurant.menu.import.store') }}" enctype="multipart/form-data" data-menu-import>
                    @csrf
                    <x-form.field name="file" :label="__('CSV file')" required>
                        <input type="file" name="file" id="field-file" accept=".csv,text/csv" @class(['form-control', 'is-invalid' => $errors->has('file')]) required>
                    </x-form.field>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> {{ __('Import') }}</button>
                    <a href="{{ route('restaurant.menu.import.template') }}" class="btn btn-outline-secondary"><i class="bi bi-download"></i> {{ __('Download a template') }}</a>
                </form>
            </x-card>
            @if ($errorsList)
                <x-card :title="__('Problems found')" icon="bi-exclamation-triangle" data-import-errors>
                    <ul class="mb-0">@foreach ($errorsList as $line)<li>{{ $line }}</li>@endforeach</ul>
                </x-card>
            @endif
        </div>
        <div class="col-xl-5">
            <x-card :title="__('Columns')" icon="bi-table">
                <dl class="small mb-0">
                    <dt>code, name, category, course</dt><dd>{{ __('Required. Course: starter, main, side, dessert or drink.') }}</dd>
                    <dt>kind</dt><dd>{{ __('dish (default), direct_stock, open or combo.') }}</dd>
                    <dt>tax_category</dt><dd>{{ __('A tax category code from Setup → Taxes, e.g. FNB.') }}</dd>
                    <dt>dietary_tags, allergens, variants</dt><dd>{{ __('Lists separated by |, e.g. halal|spicy or Half|Full.') }}</dd>
                    <dt>name_bn, description_bn …</dt><dd>{{ __('The name in each other menu language.') }}</dd>
                    <dt>price:MR, price:PB …</dt><dd>{{ __('The price at that outlet; one per variant (350|600). Empty: price left as it is.') }}</dd>
                </dl>
            </x-card>
        </div>
    </div>
</x-layouts::app>
