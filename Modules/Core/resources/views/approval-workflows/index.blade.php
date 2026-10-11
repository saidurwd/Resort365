<x-layouts::app :title="__('Approval workflows')" :subtitle="__('Who must approve which amounts')" :breadcrumbs="[__('Dashboard') => route('dashboard'), __('Approval workflows') => null]">
    <x-card body-class="p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-workflows>
                <thead><tr><th class="ps-3">{{ __('Document') }}</th><th>{{ __('Steps') }}</th><th>{{ __('Status') }}</th><th></th></tr></thead>
                <tbody>
                    @foreach ($types as $key => $type)
                        @php($workflow = $workflows[$key] ?? null)
                        <tr data-workflow="{{ $key }}">
                            <td class="ps-3"><div class="fw-semibold">{{ $type->label }}</div><div class="small text-body-secondary">{{ $type->description }}</div></td>
                            <td class="small">
                                @forelse ($workflow?->steps ?? [] as $step)
                                    <div>{{ $step->level }}. {{ $step->label }} <span class="text-body-secondary">{{ (float) $step->min_amount > 0 ? __('from :amount', ['amount' => number_format((float) $step->min_amount, 2)]) : __('any amount') }}</span></div>
                                @empty
                                    <span class="text-body-secondary">{{ __('No steps') }}</span>
                                @endforelse
                            </td>
                            <td>@if ($workflow?->is_active)<span class="badge text-bg-success">{{ __('On') }}</span>@else<span class="badge text-bg-secondary">{{ __('Off') }}</span>@endif</td>
                            <td class="text-end pe-3">@if ($workflow)<a href="{{ route('core.approval-workflows.edit', $workflow) }}" class="btn btn-sm btn-outline-secondary">{{ __('Change') }}</a>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>
</x-layouts::app>
