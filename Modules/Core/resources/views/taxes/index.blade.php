<x-layouts::app :title="__('Taxes')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Taxes') => null]">
    <div class="row">
        <div class="col-xl-8">
            <x-card :title="__('Taxes and charges')" icon="bi-percent" body-class="p-0">
                @can('core.tax.manage')
                    <x-slot:tools>
                        <a href="{{ route('core.taxes.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New tax') }}</a>
                    </x-slot:tools>
                @endcan
                @if ($taxes->isEmpty())
                    <x-empty-state icon="bi-percent" :title="__('No taxes yet')" :message="__('Add VAT, service charge or levies, then group them in categories.')" />
                @else
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" data-taxes>
                            <thead>
                                <tr>
                                    <th class="ps-3">{{ __('Order') }}</th>
                                    <th>{{ __('Tax') }}</th>
                                    <th class="text-end">{{ __('Rate') }}</th>
                                    <th>{{ __('Calculated on') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th class="pe-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($taxes as $tax)
                                    <tr>
                                        <td class="ps-3 text-body-secondary">{{ $tax->sort_order }}</td>
                                        <td><div class="fw-semibold">{{ $tax->name }}</div><div class="small text-body-secondary">{{ $tax->code }}</div></td>
                                        <td class="text-end font-monospace">{{ $tax->rateLabel() }}</td>
                                        <td>
                                            @if ($tax->type === \Modules\Core\Enums\TaxType::Fixed)
                                                {{ __('Per unit (e.g. night)') }}
                                            @else
                                                {{ $tax->is_compound ? __('Net + earlier taxes') : __('Net amount') }}
                                            @endif
                                        </td>
                                        <td>
                                            @if ($tax->is_active)
                                                <span class="badge text-bg-success">{{ __('Active') }}</span>
                                            @else
                                                <span class="badge text-bg-secondary">{{ __('Inactive') }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end pe-3 text-nowrap">
                                            @can('core.tax.manage')
                                                <a href="{{ route('core.taxes.edit', $tax) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                                <x-confirm-delete :action="route('core.taxes.destroy', $tax)" icon-only :title="__('Delete tax :name?', ['name' => $tax->name])" />
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-card>

            <x-card :title="__('Tax categories')" icon="bi-collection" body-class="p-0">
                @can('core.tax.manage')
                    <x-slot:tools>
                        <a href="{{ route('core.tax-categories.create') }}" class="btn btn-sm btn-primary"><i class="bi bi-plus-lg"></i> {{ __('New category') }}</a>
                    </x-slot:tools>
                @endcan
                @if ($categories->isEmpty())
                    <x-empty-state icon="bi-collection" :title="__('No tax categories yet')" :message="__('Rate plans, menu items and extra charges choose a category.')" />
                @else
                    <ul class="list-group list-group-flush" data-tax-categories>
                        @foreach ($categories as $category)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <div class="flex-grow-1">
                                    <span class="fw-semibold">{{ $category->name }}</span>
                                    <span class="small text-body-secondary">{{ $category->code }}</span>
                                    @unless ($category->is_active)<span class="badge text-bg-secondary">{{ __('Inactive') }}</span>@endunless
                                    <div class="small">
                                        @forelse ($category->taxes as $tax)
                                            <span @class(['badge border', 'text-bg-light' => $tax->is_active, 'text-bg-secondary' => ! $tax->is_active])>{{ $tax->name }} {{ $tax->rateLabel() }}</span>@if (! $loop->last) <i class="bi bi-arrow-right text-body-secondary"></i> @endif
                                        @empty
                                            <span class="text-body-secondary">{{ __('No taxes (exempt)') }}</span>
                                        @endforelse
                                    </div>
                                </div>
                                @can('core.tax.manage')
                                    <a href="{{ route('core.tax-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="col-xl-4">
            <x-card :title="__('Try it')" icon="bi-calculator">
                <form method="GET" action="{{ route('core.taxes.index') }}" data-tax-try>
                    <x-form.money name="amount" :label="__('Amount')" :currency="$currency" :value="request('amount', '1000.00')" required />
                    <x-form.select name="category" :label="__('Tax category')" :options="$categories->pluck('name', 'id')->all()" :value="request('category')" :placeholder="__('None')" :search="false" />
                    <x-form.input name="quantity" type="number" min="1" :label="__('Units (for fixed taxes)')" :value="request('quantity', 1)" />
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="inclusive" value="1" id="field-inclusive" @checked(request()->boolean('inclusive'))>
                        <label class="form-check-label" for="field-inclusive">{{ __('The amount includes the taxes') }}</label>
                    </div>
                    <button type="submit" class="btn btn-outline-primary w-100">{{ __('Calculate') }}</button>
                </form>

                @if ($tryError)
                    <div class="alert alert-danger mt-3 mb-0">{{ $tryError }}</div>
                @elseif ($breakdown)
                    <table class="table table-sm mt-3 mb-0" data-tax-breakdown>
                        <tr><td>{{ __('Net') }}</td><td class="text-end font-monospace">{{ number_format((float) $breakdown->net, 2) }}</td></tr>
                        @foreach ($breakdown->taxes as $line)
                            <tr><td>{{ $line->name }}</td><td class="text-end font-monospace">{{ number_format((float) $line->amount, 2) }}</td></tr>
                        @endforeach
                        <tr class="fw-semibold"><td>{{ __('Total') }} ({{ $currency }})</td><td class="text-end font-monospace">{{ number_format((float) $breakdown->gross, 2) }}</td></tr>
                    </table>
                @endif
            </x-card>
        </div>
    </div>
</x-layouts::app>
