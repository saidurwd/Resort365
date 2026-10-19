<x-layouts::app :title="$structure->name" :subtitle="$structure->code" :breadcrumbs="[__('Payroll') => null, __('Salary structures') => route('payroll.structures.index'), $structure->name => null]">
    <x-slot:actions>
        @if ($canManage)<a href="{{ route('payroll.structures.edit', $structure) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i> {{ __('Edit') }}</a>@endif
    </x-slot:actions>
    <div class="row">
        <div class="col-xl-5">
            <x-card :title="__('Components')" icon="bi-list-check" body-class="p-0">
                <table class="table table-sm align-middle mb-0" data-structure-lines>
                    <thead><tr><th class="ps-3">{{ __('Component') }}</th><th>{{ __('Worked out as') }}</th></tr></thead>
                    <tbody>
                        @foreach ($structure->lines as $line)
                            <tr data-line="{{ $line->component->code }}">
                                <td class="ps-3"><span class="font-monospace">{{ $line->component->code }}</span> <span class="small text-body-secondary">{{ $line->component->name }}</span> <x-status-badge :status="$line->component->kind" /></td>
                                <td class="small">
                                    @if ($line->component->code === 'TAX'){{ __('Income tax table') }}
                                    @elseif ($line->component->code === 'BASIC'){{ __('The employee\'s basic salary') }}
                                    @else
                                        @switch($line->calc_type->value)
                                            @case('fixed'){{ number_format((float) $line->amount, 2) }}@break
                                            @case('employee'){{ __('Set for each employee') }} ({{ number_format((float) $line->amount, 2) }})@break
                                            @case('percent'){{ rtrim(rtrim((string) $line->percent, '0'), '.') }}% {{ __('of') }} {{ $line->percent_of }}@break
                                            @default<span class="font-monospace">{{ $line->formula }}</span>
                                        @endswitch
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-card>
        </div>
        <div class="col-xl-7">
            <x-card :title="__('Try it out')" icon="bi-calculator">
                <form method="GET" action="{{ route('payroll.structures.show', $structure) }}" class="row g-2 align-items-end mb-3" data-preview-form>
                    <div class="col-md-3"><x-form.input name="basic" type="number" step="0.01" min="0" :label="__('Basic salary')" :value="$basic" wrapper-class="mb-0" /></div>
                    <div class="col-md-3"><x-form.select name="category" :label="__('Taxpayer')" :options="$categories" :value="$category" wrapper-class="mb-0" :search="false" /></div>
                    <div class="col-md-3"><x-form.select name="area" :label="__('Tax area')" :options="$areas" :value="$area" wrapper-class="mb-0" :search="false" /></div>
                    <div class="col-md-3"><button type="submit" class="btn btn-primary w-100">{{ __('Work it out') }}</button></div>
                </form>
                @if ($error)
                    <div class="alert alert-danger" data-preview-error>{{ $error }}</div>
                @else
                    @include('payroll::partials.breakdown', ['breakdown' => $breakdown])
                @endif
            </x-card>
        </div>
    </div>
</x-layouts::app>
